<?php

class Helpers {

	public static $author = "IDPay";

	public static function exists( $array, $key ): bool {
		if ( $array instanceof ArrayAccess ) {
			return $array->offsetExists( $key );
		}

		if ( is_float( $key ) ) {
			$key = (string) $key;
		}

		return array_key_exists( $key, $array );
	}

	public static function accessible( $value ): bool {
		return is_array( $value ) || $value instanceof ArrayAccess;
	}

	public static function value( $value, ...$args ) {
		return $value instanceof Closure ? $value( ...$args ) : $value;
	}

	public static function collapse( $array ): array {
		$results = [];

		foreach ( $array as $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			$results[] = $values;
		}

		return array_merge( [], ...$results );
	}

	public static function dataGet( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$key = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $key as $i => $segment ) {
			unset( $key[ $i ] );

			if ( is_null( $segment ) ) {
				return $target;
			}

			if ( $segment === '*' ) {
				if ( ! is_iterable( $target ) ) {
					return self::value( $default );
				}

				$result = [];

				foreach ( $target as $item ) {
					$result[] = self::dataGet( $item, $key );
				}

				return in_array( '*', $key ) ? self::collapse( $result ) : $result;
			}

			if ( self::accessible( $target ) && self::exists( $target, $segment ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return self::value( $default );
			}
		}

		return $target;
	}

	public static function Return_URL( $form_id, $entry_id ) {
		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		if ( $_SERVER['SERVER_PORT'] != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$arr_params = array( 'id', 'entry', 'no', 'Authority', 'Status' );
		$pageURL    = esc_url( remove_query_arg( $arr_params, $pageURL ) );

		$pageURL = str_replace( '#038;', '&', add_query_arg( array(
			'form_id' => $form_id,
			'entry'   => $entry_id
		), $pageURL ) );

		return apply_filters( self::$author . '_IDPay_return_url', apply_filters( self::$author . '_gateway_return_url', $pageURL, $form_id, $entry_id, __CLASS__ ), $form_id, $entry_id, __CLASS__ );
	}

	public static function redirect_confirmation( $url, $ajax ) {
		if ( headers_sent() || $ajax ) {
			$confirmation = "<script type=\"text/javascript\">" . apply_filters( 'gform_cdata_open', '' ) . " function gformRedirect(){document.location.href='$url';}";
			if ( ! $ajax ) {
				$confirmation .= 'gformRedirect();';
			}
			$confirmation .= apply_filters( 'gform_cdata_close', '' ) . '</script>';
		} else {
			$confirmation = array( 'redirect' => $url );
		}

		return $confirmation;
	}

	public static function getGravityTransactionTypeCode( $type ): int {
		return $type == "subscription" ? 2 : 1;
	}

	public static function checkOneConfirmationExists( $confirmation, $form, $entry, $ajax ): bool {
		if ( apply_filters(
			'gf_IDPay_request_return',
			apply_filters( 'gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax ),
			$confirmation,
			$form,
			$entry,
			$ajax
		) ) {
			return false;
		}

		return true;
	}

	public static function checkSubmittedForIDPay( $formId ): bool {
		if ( RGForms::post( "gform_submit" ) != $formId ) {
			return false;
		}

		return true;
	}

	public static function checkFeedExists( $form ): bool {
		return ! empty( IDPayDB::getActiveFeed( $form ) );
	}

	public static function getGatewayName(): string {
		$settings = get_option( "gf_IDPay_settings" );

		return isset( $settings['gname'] ) ? $settings["gname"] : __( 'IDPay', 'gravityformsIDPay' );
	}

	public static function getFeed( $form ) {
		return IDPayDB::getActiveFeed( $form );
	}

	public static function fixPrice( $amount, $form, $entry ): int {
		return GFPersian_Payments::amount( $amount, 'IRR', $form, $entry );
	}

	public static function isNotApprovedPrice( $amount ): int {
		return empty( $amount ) || $amount > 500000000 || $amount < 1000;
	}

	public static function getApiKey() {
		$settings = get_option( "gf_IDPay_settings" );
		$api_key  = $settings["api_key"] ?? '';

		return trim( $api_key );
	}

	public static function getSandbox() {
		$settings = get_option( "gf_IDPay_settings" );

		return $settings["sandbox"] ? "true" : "false";
	}

	public static function httpRequest( $url, $data ) {
		$args = [
			'body'    => json_encode( $data ),
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-KEY'    => self::getApiKey(),
				'X-SANDBOX'    => self::getSandbox(),
			],
			'timeout' => 30,
		];

		$number_of_connection_tries = 3;
		while ( $number_of_connection_tries ) {
			$response = wp_safe_remote_post( $url, $args );
			if ( is_wp_error( $response ) ) {
				$number_of_connection_tries --;
			} else {
				break;
			}
		}

		return $response;
	}
}
