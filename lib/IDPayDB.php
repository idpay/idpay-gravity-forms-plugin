<?php

class IDPayDB {
	public static $author = "IDPay";
	public const METHOD_FEEDS = 'getFeeds';
	public const METHOD_TRANSACTIONS = 'getFeeds';


	public static function unSerializeDto( $dto ) {
		$count = sizeof( $dto );
		for ( $i = 0; $i < $count; $i ++ ) {
			$dto[ $i ]["meta"] = maybe_unserialize( $dto[ $i ]["meta"] );
		}

		return $dto;
	}

	public static function castAndNormalizeDto( $dto ) {
		if ( ! is_array( $dto ) ) {
			return [];
		}

		$list = [];
		foreach ( $dto as $item ) {
			$status          = $item["status"];
			$revenue         = ! empty( $item["revenue"] ) ? $item["revenue"] : 0;
			$transactions    = ! empty( $item["transactions"] ) ? $item["transactions"] : 0;
			$list[ $status ] = [ "revenue" => $revenue, "transactions" => $transactions ];
		}

		return $list;
	}

	public static function getActiveFeed( $form ) {
		$configs = IDPayDB::getFeedByFormId( $form["id"] );
		$configs = apply_filters(
			self::$author . '_gf_IDPay_get_active_configs',
			apply_filters( self::$author . '_gf_gateway_get_active_configs', $configs, $form ),
			$form
		);

		return $configs;
	}

	public static function getFeedByFormId( $formId ) {
		global $wpdb;
		$tableName = self::getTableName();
		$query     = "SELECT * FROM {$tableName} WHERE form_id={$formId}";
		$results   = $wpdb->get_results( $query, ARRAY_A );

		return ! empty( $results ) == true ? self::unSerializeDto( $results ) : [];
	}

	public static function getTableName() {
		global $wpdb;

		return $wpdb->prefix . "gf_IDPay";
	}

	public static function updateFeed( $id, $formId, $setting ) {
		global $wpdb;
		$dto = [
			"form_id"   => $formId,
			"is_active" => true,
			"meta"      => maybe_serialize( $setting )
		];

		if ( $id == 0 ) {
			$wpdb->insert( self::getTableName(), $dto, [ "%d", "%d", "%s" ] );
			$id = $wpdb->get_var( "SELECT LAST_INSERT_ID()" );
		}
		if ( $id != 0 ) {
			$wpdb->update( self::getTableName(), $dto, [ "id" => $id ], [ "%d", "%d", "%s" ], [ "%d" ] );
		}

		return $id;
	}

	public static function deleteFeed( $id ) {
		global $wpdb;
		$tableName = self::getTableName();
		$query     = "DELETE FROM {$tableName} WHERE id={$id}";
		$wpdb->query( $query );
	}

	public static function getFeed( $id ) {
		global $wpdb;
		$tableName = self::getTableName();
		$query     = "SELECT id, form_id, is_active, meta FROM {$tableName} WHERE id={$id}";
		$results   = $wpdb->get_results( $query, ARRAY_A );
		$results   = ! empty( $results ) == true ? self::unSerializeDto( $results ) : [];

		return $results[0];
	}

	public static function getFeeds( $pagination ) {
		global $wpdb;
		$limitRowsMin = ! empty( $pagination->query->min ) ? $pagination->query->min : 0;
		$limitRowsMax = ! empty( $pagination->query->max ) ? $pagination->query->max : $pagination->query->count;
		$IDPayTable   = self::getTableName();
		$formTable    = RGFormsModel::get_form_table_name();
		$query        = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                  FROM ( SELECT * FROM {$IDPayTable} LIMIT {$limitRowsMin},{$limitRowsMax} ) s
                  INNER JOIN {$formTable} f ON s.form_id = f.id  ORDER BY s.id";
		$results      = $wpdb->get_results( $query, ARRAY_A );

		return ! empty( $results ) == true ? self::unSerializeDto( $results ) : [];
	}

	public static function getFeedsCount() {
		global $wpdb;
		$IDPayTable = self::getTableName();
		$formTable  = RGFormsModel::get_form_table_name();
		$query      = "SELECT count(*) as count FROM {$IDPayTable}";
		$results    = $wpdb->get_results( $query, ARRAY_A );

		return ! empty( $results ) == true ? (int) $results[0]['count'] : 0;
	}

	public static function dropTable() {
		global $wpdb;
		$tableName = self::getTableName();
		$query     = "DROP TABLE IF EXISTS {$tableName}";
		$wpdb->query( $query );
	}

	public static function getTransactionTableName() {
		$version = GFCommon::$version;
		if ( method_exists( 'GFFormsModel', 'get_database_version' ) ) {
			$version = GFFormsModel::get_database_version();
		}
		$condition = version_compare( $version, '2.3-dev-1', '<' );

		return $condition ? GFFormsModel::get_lead_table_name() : GFFormsModel::get_entry_table_name();
	}

	public static function upgrade() {
		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

		global $wpdb;
		$oldTable         = $wpdb->prefix . "rg_IDPay";
		$tableName        = self::getTableName();
		$queryShowTable   = "SHOW TABLES LIKE '{$oldTable}'";
		$queryRenameTable = "ALTER TABLE {$oldTable} RENAME {$tableName}";
		$existsTable      = ! empty( $wpdb->get_var( $queryShowTable ) );
		$charset          = ! empty( $wpdb->charset ) ? $wpdb->charset : null;
		$collate          = ! empty( $wpdb->collate ) ? $wpdb->collate : null;
		$options          = ! empty( $charset ) ? "DEFAULT CHARACTER SET {$charset}" : "";
		$options          .= ! empty( $collate ) ? " COLLATE {$collate}" : "";

		$queryCreateTable = "CREATE TABLE IF NOT EXISTS {$tableName} (
              id mediumint(8) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              is_active tinyint(1) not null default 1,
              meta longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
		) {$options};";


		if ( $existsTable == true ) {
			$wpdb->query( $queryRenameTable );

			return 'completed rename table';
		} else {
			dbDelta( $queryCreateTable );

			return 'completed create table';
		}

	}

	public static function runAnalyticsQuery( $query ) {
		global $wpdb;
		$transactionTableName = self::getTransactionTableName();
		$query                = sprintf( $query, $transactionTableName );
		$results              = $wpdb->get_results( $query, ARRAY_A );

		return self::castAndNormalizeDto( $results );
	}

	public static function prepareAnalyticsQuery() {
		return " SELECT status, sum(payment_amount) revenue, count(id) transactions FROM %s ";
	}

	public static function getAnalyticsTotalTransactions( $formId ) {
		$query = self::prepareAnalyticsQuery();
		$query .= "
			  WHERE form_id={$formId} 
	            AND status='active' 
	            AND is_fulfilled=1 
	            AND payment_method='IDPay'
	          GROUP BY status";

		return self::runAnalyticsQuery( $query );
	}

}
