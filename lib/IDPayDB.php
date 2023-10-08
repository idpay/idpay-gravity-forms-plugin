<?php

class IDPayDB extends Helpers {
	public static function getSqlQuery( $filename ) {
		$basePath = Helpers::getBasePath();
		$basePath = "{$basePath}/sql";

		return file_get_contents( "{$basePath}/{$filename}.sql" );
	}

	public static function prepareQuery( $type, $filters ) {
		$transactionTableName = IDPayDB::getTransactionTableName();
		$formTable            = RGFormsModel::get_form_table_name();
		$IDPayTable           = IDPayDB::getTableName();

		switch ( $type ) {
			case Keys::QUERY_ANALYTICS:
				return sprintf(
					IDPayDB::getSqlQuery( 'analytics' ),
					$transactionTableName,
					$filters->formId
				);

			case Keys::QUERY_COUNT_FEED:
				return sprintf(
					IDPayDB::getSqlQuery( 'count_feed' ),
					$IDPayTable
				);

			case Keys::QUERY_ADD_META_COLUMN:
				return sprintf(
					IDPayDB::getSqlQuery( 'add_meta_column' ),
					$IDPayTable,
					$filters->column
				);

			case Keys::QUERY_CLONE_META_COLUMN:
				return sprintf(
					IDPayDB::getSqlQuery( 'clone_meta_column' ),
					$IDPayTable,
					$filters->endColumn,
					$filters->StartColumn
				);

			case Keys::QUERY_DELETE_META_COLUMN:
				return sprintf(
					IDPayDB::getSqlQuery( 'delete_meta_column' ),
					$IDPayTable,
					$filters->column
				);

			case Keys::QUERY_FEED_BY_ID:
				return sprintf(
					IDPayDB::getSqlQuery( 'feed_by_id' ),
					$IDPayTable,
					$filters->formId
				);

			case Keys::QUERY_DELETE_FEED:
				return sprintf(
					IDPayDB::getSqlQuery( 'delete_feed' ),
					$IDPayTable,
					$filters->id
				);

			case Keys::QUERY_FEED:
				return sprintf(
					IDPayDB::getSqlQuery( 'feed' ),
					$IDPayTable,
					$filters->id
				);

			case Keys::QUERY_FEEDS:
				return sprintf(
					IDPayDB::getSqlQuery( 'feeds' ),
					$IDPayTable,
					$filters->limitRowsMin,
					$filters->limitRowsMax,
					$formTable
				);

			case Keys::QUERY_ALL_FEEDS:
				return sprintf(
					IDPayDB::getSqlQuery( 'all_feeds' ),
					$IDPayTable,
				);

			case Keys::QUERY_FORM:
				return sprintf(
					IDPayDB::getSqlQuery( 'form' ),
					$formTable,
					$filters->formId
				);

			case Keys::QUERY_DELETE_IDPAY:
				return sprintf(
					IDPayDB::getSqlQuery( 'delete_idpay' ),
					$IDPayTable
				);

			case Keys::QUERY_COUNT_TRANSACTION:
				return sprintf(
					IDPayDB::getSqlQuery( 'count_transactions' ),
					$transactionTableName,
					$filters->formId
				);


			case Keys::QUERY_TRANSACTIONS:
				return sprintf(
					IDPayDB::getSqlQuery( 'transactions' ),
					$transactionTableName,
					$filters->formId,
					$filters->limitRowsMin,
					$filters->limitRowsMax
				);

			case Keys::QUERY_CHECK_META_COLUMN:
				return sprintf(
					IDPayDB::getSqlQuery( 'check_meta_column' ),
					$filters->db,
					$IDPayTable,
					$filters->column,
				);
		}
	}

	public static function castAndNormalizeDto( $dto, $type ) {
		if ( ! is_array( $dto ) ) {
			return [];
		}

		if ( $type == Keys::QUERY_ANALYTICS ) {
			$list = [];
			foreach ( $dto as $item ) {
				$status          = $item["status"];
				$revenue         = ! empty( $item["revenue"] ) ? $item["revenue"] : 0;
				$transactions    = ! empty( $item["transactions"] ) ? $item["transactions"] : 0;
				$list[ $status ] = [ "revenue" => $revenue, "transactions" => $transactions ];
			}

			return $list;
		} elseif ( $type == Keys::QUERY_COUNT_TRANSACTION ||
		           $type == Keys::QUERY_COUNT_FEED ||
		           $type == Keys::QUERY_CHECK_META_COLUMN ) {
			return ! empty( $dto ) == true ? ( (int) $dto[0]['count'] ) : 0;
		} else {
			return $dto;
		}
	}

	public static function runQuery( $query, $type ) {
		global $wpdb;
		$results = $wpdb->get_results( $query, ARRAY_A );

		return IDPayDB::castAndNormalizeDto( $results, $type );
	}

	public static function unSerializeDto( $dto ) {
		$count = sizeof( $dto );
		for ( $i = 0; $i < $count; $i ++ ) {
			$dto[ $i ]["meta"] = maybe_unserialize( $dto[ $i ]["meta"] );
		}

		return $dto;
	}

	public static function getActiveFeed( $form ) {
		$configs = IDPayDB::getFeedByFormId( $form["id"] );
		$configs = apply_filters(
			Keys::AUTHOR . '_gf_IDPay_get_active_configs',
			apply_filters( Keys::AUTHOR . '_gf_gateway_get_active_configs', $configs, $form ),
			$form
		);

		return $configs;
	}

	public static function getTableName() {
		global $wpdb;

		return $wpdb->prefix . "gf_IDPay";
	}

	public static function SaveOrUpdateFeed() {
		check_ajax_referer( 'gf_IDPay_update_feed_active', 'gf_IDPay_update_feed_active' );
		$id   = absint( rgpost( 'feed_id' ) );
		$feed = IDPayDB::getFeed( $id );
		IDPayDB::updateFeed( $id, $feed["form_id"], $feed["meta"] );
	}

	public static function updateFeed( $id, $formId, $setting ) {
		global $wpdb;
		$dto = [
			"form_id"   => $formId,
			"is_active" => true,
			"meta"      => maybe_serialize( $setting )
		];

		if ( $id == 0 ) {
			$wpdb->insert( IDPayDB::getTableName(), $dto, [ "%d", "%d", "%s" ] );
			$id = $wpdb->get_var( "SELECT LAST_INSERT_ID()" );
		}
		if ( $id != 0 ) {
			$wpdb->update( IDPayDB::getTableName(), $dto, [ "id" => $id ], [ "%d", "%d", "%s" ], [ "%d" ] );
		}

		return $id;
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
		$tableName        = IDPayDB::getTableName();
		$queryShowTable   = sprintf( IDPayDB::getSqlQuery( 'show_table' ), $oldTable );
		$queryRenameTable = sprintf( IDPayDB::getSqlQuery( 'alter_table' ), $oldTable, $tableName );
		$existsTable      = ! empty( $wpdb->get_var( $queryShowTable ) );
		$charset          = ! empty( $wpdb->charset ) ? $wpdb->charset : null;
		$collate          = ! empty( $wpdb->collate ) ? $wpdb->collate : null;
		$options          = ! empty( $charset ) ? "DEFAULT CHARACTER SET {$charset}" : "";
		$options          .= ! empty( $collate ) ? " COLLATE {$collate}" : "";

		$queryCreateTable = sprintf(
			IDPayDB::getSqlQuery( 'create' ),
			$tableName,
			$options
		);

		$existsTable == true ? $wpdb->query( $queryRenameTable ) : dbDelta( $queryCreateTable );
	}

	public static function checkMetaOldColumnExist() {
		$type    = Keys::QUERY_CHECK_META_COLUMN;
		$filters = (object) [
			'db'     => DB_NAME,
			'column' => 'meta_old',
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function addMetaColumn() {
		$type    = Keys::QUERY_ADD_META_COLUMN;
		$filters = (object) [
			'db'     => DB_NAME,
			'column' => 'meta_old',
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function makeBackupMetaColumn() {
		$type    = Keys::QUERY_CLONE_META_COLUMN;
		$filters = (object) [
			'StartColumn' => 'meta',
			'endColumn'   => 'meta_old',
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function makeRestoreMetaColumn() {
		$type    = Keys::QUERY_CLONE_META_COLUMN;
		$filters = (object) [
			'StartColumn' => 'meta_old',
			'endColumn'   => 'meta',
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function deleteMetaColumn() {
		$type    = Keys::QUERY_DELETE_META_COLUMN;
		$filters = (object) [
			'db'     => DB_NAME,
			'column' => 'meta_old',
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function getFeeds( $pagination, $filters ) {

		$limitRowsMin = ! empty( $pagination->query->min ) ? $pagination->query->min : 0;
		$limitRowsMax = ! empty( $pagination->query->max ) ? $pagination->query->max : $pagination->query->count;

		$type    = Keys::QUERY_FEEDS;
		$filters = (object) [
			'limitRowsMin' => $limitRowsMin,
			'limitRowsMax' => $limitRowsMax,
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );

		return ! empty( $results ) == true ? IDPayDB::unSerializeDto( $results ) : [];
	}

	public static function getAllFeeds() {
		$type    = Keys::QUERY_ALL_FEEDS;
		$filters = (object) [];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );

		return ! empty( $results ) == true ? IDPayDB::unSerializeDto( $results ) : [];
	}

	public static function getAnalyticsTotalTransactions( $formId ) {
		$type    = Keys::QUERY_ANALYTICS;
		$filters = (object) [ 'formId' => $formId ];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function getTransactionsCount( $filters ) {
		$type  = Keys::QUERY_COUNT_TRANSACTION;
		$query = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function getFeedsCount( $filters ) {
		$type  = Keys::QUERY_COUNT_FEED;
		$query = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function dropTable() {

		$type    = Keys::QUERY_DELETE_IDPAY;
		$filters = (object) [];
		$query   = IDPayDB::prepareQuery( $type, $filters );

		return IDPayDB::runQuery( $query, $type );
	}

	public static function getFeedByFormId( $formId ) {
		$type    = Keys::QUERY_FEED_BY_ID;
		$filters = (object) [ 'formId' => $formId ];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );

		return ! empty( $results ) == true ? IDPayDB::unSerializeDto( $results ) : [];
	}

	public static function deleteFeed( $id ) {
		$type    = Keys::QUERY_DELETE_FEED;
		$filters = (object) [ 'id' => $id ];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );
	}

	public static function getFeed( $id ) {
		$type    = Keys::QUERY_FEED;
		$filters = (object) [ 'id' => $id ];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );
		$results = ! empty( $results ) == true ? IDPayDB::unSerializeDto( $results ) : [];

		return $results[0];
	}

	public static function getForm( $formId ) {
		$type    = Keys::QUERY_FORM;
		$filters = (object) [ 'formId' => $formId ];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );

		return $results[0];
	}


	public static function getTransactions( $pagination, $filters ) {

		$limitRowsMin = ! empty( $pagination->query->min ) ? $pagination->query->min : 0;
		$limitRowsMax = ! empty( $pagination->query->max ) ? $pagination->query->max : $pagination->query->count;

		$type    = Keys::QUERY_TRANSACTIONS;
		$filters = (object) [
			'limitRowsMin' => $limitRowsMin,
			'limitRowsMax' => $limitRowsMax,
			'formId'       => $filters->formId
		];
		$query   = IDPayDB::prepareQuery( $type, $filters );
		$results = IDPayDB::runQuery( $query, $type );

		return ! empty( $results ) == true ? $results : [];
	}


	public static function getWithPaginate( $methodName, $filters ) {
		$pagination = IDPayDB::loadPagination( $methodName, $filters );
		$data       = IDPayDB::loadData( $methodName, $pagination, $filters );

		return (object) [
			'query' => (object) [
				'min'   => $pagination->query->min,
				'max'   => $pagination->query->max,
				'count' => $pagination->query->count,
			],
			'data'  => $data,
			'meta'  => $pagination->html,
		];
	}

	public static function loadData( $methodName, $pagination, $filters = [] ) {
		return IDPayDB::{$methodName}( $pagination, $filters );
	}

	public static function loadPagination( $methodName, $filters ) {
		global $wp;
		$dict = Helpers::loadDictionary();
		$labelfPage = $dict->labelNextPage;
		$labelbPage = $dict->labelBackPage;
		$adminUrl = admin_url( $wp->request ) . 'admin.php';
		$currentUrl      = add_query_arg( $_SERVER['QUERY_STRING'], '', $adminUrl );
		$number = sanitize_text_field( rgget( 'page_number' ) );
		$pageNumber      = rgget( 'page_number' ) != '' ? $number : 1;
		$countMethodName = "{$methodName}Count";
		$count           = IDPayDB::{$countMethodName}( $filters );
		$limit           = 50;
		$min             = $pageNumber == 1 ? 0 : ( $pageNumber - 1 ) * $limit;
		$max             = $pageNumber * $limit;
		$paginationHtml  = "<ul style='display: flex;justify-content: center'>";
		$fPage           = $pageNumber + 1;
		$bPage           = $pageNumber - 1;
		if ( $pageNumber > 1 ) {
			$paginationHtml .= "<li><a class='button' href='$currentUrl&page_number=$bPage'>{$labelbPage}</a></li>";
		}
		if ( ( ( $count / $limit ) / $pageNumber ) > 1 ) {
			$paginationHtml .= "<li><a class='button' href='$currentUrl&page_number=$fPage'>{$labelfPage}</a></li>";
		}

		$paginationHtml .= '</ul>';

		return (object) [
			'query' => (object) [
				'min'   => $min,
				'max'   => $max,
				'count' => $count,
			],
			'html'  => $paginationHtml,
		];
	}
}
