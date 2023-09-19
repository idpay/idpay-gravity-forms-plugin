<?php

class IDPayDB
{
    public static $author = "IDPay";

    public const FEEDS = 'getFeeds';
    public const TRANSACTIONS = 'getTransactions';

    public const QUERY_TRANSACTIONS = 'QUERY_TRANSACTIONS';
    public const QUERY_ANALYTICS = 'QUERY_ANALYTICS';
    public const QUERY_COUNT_TRANSACTION = 'QUERY_COUNT_TRANSACTION';
    public const QUERY_COUNT_FEED = 'QUERY_COUNT_FEED';
    public const QUERY_DELETE_IDPAY = 'QUERY_DELETE_IDPAY';
    public const QUERY_FEED_BY_ID = 'QUERY_FEED_BY_ID';
    public const QUERY_DELETE_FEED = 'QUERY_DELETE_FEED';
    public const QUERY_FEED = 'QUERY_FEED';
    public const QUERY_FEEDS = 'QUERY_FEEDS';
    public const QUERY_FORM = 'QUERY_FORM';

    public static function getSqlQuery($filename)
    {
	    $basePath = Helpers::getBasePath();
	    $basePath = "{$basePath}/sql";
        return file_get_contents("{$basePath}/{$filename}.sql");
    }

    public static function prepareQuery($type, $filters)
    {
        $transactionTableName = self::getTransactionTableName();
        $formTable    = RGFormsModel::get_form_table_name();
        $IDPayTable = self::getTableName();

        switch ($type) {
            case self::QUERY_ANALYTICS:
                return sprintf(
                    self::getSqlQuery('analytics'),
                    $transactionTableName,
                    $filters->formId
                );

            case self::QUERY_COUNT_FEED:
                return sprintf(
                    self::getSqlQuery('count_feed'),
                    $IDPayTable
                );

            case self::QUERY_FEED_BY_ID:
                return sprintf(
                    self::getSqlQuery('feed_by_id'),
                    $IDPayTable,
                    $filters->formId
                );

            case self::QUERY_DELETE_FEED:
                return sprintf(
                    self::getSqlQuery('delete_feed'),
                    $IDPayTable,
                    $filters->id
                );

            case self::QUERY_FEED:
                return sprintf(
                    self::getSqlQuery('feed'),
                    $IDPayTable,
                    $filters->id
                );

            case self::QUERY_FEEDS:
                return sprintf(
                    self::getSqlQuery('feeds'),
                    $IDPayTable,
                    $filters->limitRowsMin,
                    $filters->limitRowsMax,
                    $formTable
                );

            case self::QUERY_FORM:
                return sprintf(
                    self::getSqlQuery('form'),
                    $formTable,
                    $filters->formId
                );

            case self::QUERY_DELETE_IDPAY:
                return sprintf(
                    self::getSqlQuery('delete_idpay'),
                    $IDPayTable
                );

            case self::QUERY_COUNT_TRANSACTION:
                return sprintf(
                    self::getSqlQuery('count_transactions'),
                    $transactionTableName,
                    $filters->formId
                );


            case self::QUERY_TRANSACTIONS:
                return sprintf(
                    self::getSqlQuery('transactions'),
                    $transactionTableName,
                    $filters->formId,
                    $filters->limitRowsMin,
                    $filters->limitRowsMax
                );
        }
    }

    public static function castAndNormalizeDto($dto, $type)
    {
        if (! is_array($dto)) {
            return [];
        }

        if ($type == self::QUERY_ANALYTICS) {
            $list = [];
            foreach ($dto as $item) {
                $status          = $item["status"];
                $revenue         = ! empty($item["revenue"]) ? $item["revenue"] : 0;
                $transactions    = ! empty($item["transactions"]) ? $item["transactions"] : 0;
                $list[ $status ] = [ "revenue" => $revenue, "transactions" => $transactions ];
            }

            return $list;
        } elseif ($type == self::QUERY_COUNT_TRANSACTION || $type == self::QUERY_COUNT_FEED) {
            return !empty($dto) == true ? ( (int) $dto[0]['count'] ) : 0;
        } else {
            return $dto;
        }
    }

    public static function runQuery($query, $type)
    {
        global $wpdb;
        $results = $wpdb->get_results($query, ARRAY_A);
        return self::castAndNormalizeDto($results, $type);
    }

    public static function unSerializeDto($dto)
    {
        $count = sizeof($dto);
        for ($i = 0; $i < $count; $i ++) {
            $dto[ $i ]["meta"] = maybe_unserialize($dto[ $i ]["meta"]);
        }

        return $dto;
    }

    public static function getActiveFeed($form)
    {
        $configs = IDPayDB::getFeedByFormId($form["id"]);
        $configs = apply_filters(
            self::$author . '_gf_IDPay_get_active_configs',
            apply_filters(self::$author . '_gf_gateway_get_active_configs', $configs, $form),
            $form
        );

        return $configs;
    }

    public static function getTableName()
    {
        global $wpdb;

        return $wpdb->prefix . "gf_IDPay";
    }

	public static function SaveOrUpdateFeed() {
		check_ajax_referer( 'gf_IDPay_update_feed_active', 'gf_IDPay_update_feed_active' );
		$id   = absint( rgpost( 'feed_id' ) );
		$feed = IDPayDB::getFeed( $id );
		IDPayDB::updateFeed( $id, $feed["form_id"], $feed["meta"] );
	}

    public static function updateFeed($id, $formId, $setting)
    {
        global $wpdb;
        $dto = [
            "form_id"   => $formId,
            "is_active" => true,
            "meta"      => maybe_serialize($setting)
        ];

        if ($id == 0) {
            $wpdb->insert(self::getTableName(), $dto, [ "%d", "%d", "%s" ]);
            $id = $wpdb->get_var("SELECT LAST_INSERT_ID()");
        }
        if ($id != 0) {
            $wpdb->update(self::getTableName(), $dto, [ "id" => $id ], [ "%d", "%d", "%s" ], [ "%d" ]);
        }

        return $id;
    }

    public static function getTransactionTableName()
    {
        $version = GFCommon::$version;
        if (method_exists('GFFormsModel', 'get_database_version')) {
            $version = GFFormsModel::get_database_version();
        }
        $condition = version_compare($version, '2.3-dev-1', '<');

        return $condition ? GFFormsModel::get_lead_table_name() : GFFormsModel::get_entry_table_name();
    }

    public static function upgrade()
    {
        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

        global $wpdb;
        $oldTable         = $wpdb->prefix . "rg_IDPay";
        $tableName        = self::getTableName();
        $queryShowTable   = sprintf( self::getSqlQuery('show_table'), $oldTable );
        $queryRenameTable = sprintf( self::getSqlQuery('alter_table'), $oldTable, $tableName );
        $existsTable      = ! empty($wpdb->get_var($queryShowTable));
        $charset          = ! empty($wpdb->charset) ? $wpdb->charset : null;
        $collate          = ! empty($wpdb->collate) ? $wpdb->collate : null;
        $options          = ! empty($charset) ? "DEFAULT CHARACTER SET {$charset}" : "";
        $options          .= ! empty($collate) ? " COLLATE {$collate}" : "";

        $queryCreateTable = sprintf(
			self::getSqlQuery('create'),
			$tableName,
			$options
        );

		$existsTable == true ? $wpdb->query($queryRenameTable) : dbDelta($queryCreateTable);
	    delete_option("gf_IDPay_version");

    }

    public static function getFeeds($pagination, $filters)
    {

        $limitRowsMin = ! empty($pagination->query->min) ? $pagination->query->min : 0;
        $limitRowsMax = ! empty($pagination->query->max) ? $pagination->query->max : $pagination->query->count;

        $type  = self::QUERY_FEEDS;
        $filters = (object)[
            'limitRowsMin' => $limitRowsMin,
            'limitRowsMax' => $limitRowsMax,
        ];
        $query = self::prepareQuery($type, $filters);
        $results = self::runQuery($query, $type);
        return ! empty($results) == true ? self::unSerializeDto($results) : [];
    }

    public static function getAnalyticsTotalTransactions($formId)
    {
        $type  = self::QUERY_ANALYTICS;
        $filters = (object)[ 'formId' => $formId];
        $query = self::prepareQuery($type, $filters);
        return self::runQuery($query, $type);
    }

    public static function getTransactionsCount($filters)
    {
        $type  = self::QUERY_COUNT_TRANSACTION;
        $query = self::prepareQuery($type, $filters);
        return self::runQuery($query, $type);
    }

    public static function getFeedsCount($filters)
    {
        $type  = self::QUERY_COUNT_FEED;
        $query = self::prepareQuery($type, $filters);
        return self::runQuery($query, $type);
    }

    public static function dropTable()
    {

        $type  = self::QUERY_DELETE_IDPAY;
        $filters = (object)[];
        $query = self::prepareQuery($type, $filters);
        return self::runQuery($query, $type);
    }

    public static function getFeedByFormId($formId)
    {
        $type  = self::QUERY_FEED_BY_ID;
        $filters = (object)[ 'formId' => $formId];
        $query = self::prepareQuery($type, $filters);
        $results =  self::runQuery($query, $type);
        return ! empty($results) == true ? self::unSerializeDto($results) : [];
    }

    public static function deleteFeed($id)
    {
        $type  = self::QUERY_DELETE_FEED;
        $filters = (object)[ 'id' => $id];
        $query = self::prepareQuery($type, $filters);
        $results =  self::runQuery($query, $type);
    }

    public static function getFeed($id)
    {
        $type  = self::QUERY_FEED;
        $filters = (object)[ 'id' => $id];
        $query = self::prepareQuery($type, $filters);
        $results =  self::runQuery($query, $type);
        $results   = ! empty($results) == true ? self::unSerializeDto($results) : [];
        return $results[0];
    }

    public static function getForm($formId)
    {
        $type    = self::QUERY_FORM;
        $filters = (object) [ 'formId' => $formId ];
        $query   = self::prepareQuery($type, $filters);
        $results = self::runQuery($query, $type);

        return $results[0];
    }


    public static function getTransactions($pagination, $filters)
    {

        $limitRowsMin = ! empty($pagination->query->min) ? $pagination->query->min : 0;
        $limitRowsMax = ! empty($pagination->query->max) ? $pagination->query->max : $pagination->query->count;

        $type    = self::QUERY_TRANSACTIONS;
        $filters = (object) [
            'limitRowsMin' => $limitRowsMin,
            'limitRowsMax' => $limitRowsMax,
            'formId' => $filters->formId
        ];
        $query   = self::prepareQuery($type, $filters);
        $results = self::runQuery($query, $type);

        return ! empty($results) == true ? $results : [];
    }


    public static function getWithPaginate($methodName, $filters)
    {
        $pagination = self::loadPagination($methodName, $filters);
        $data       = self::loadData($methodName, $pagination, $filters);

        return (object) [
            'query' => (object) [
                'min'     => $pagination->query->min,
                'max'     => $pagination->query->max,
                'count'   => $pagination->query->count,
            ],
            'data' => $data,
            'meta'  => $pagination->html,
        ];
    }

    public static function loadData($methodName, $pagination, $filters = [])
    {
        return self::{$methodName}($pagination, $filters);
    }

    public static function loadPagination($methodName, $filters)
    {
        global $wp;
        $currentUrl = add_query_arg($_SERVER['QUERY_STRING'], '', admin_url($wp->request) . 'admin.php');
        $pageNumber     = rgget('page_number') != '' ? sanitize_text_field(rgget('page_number')) : 1;
        $countMethodName     = "{$methodName}Count";
        $count          = self::{$countMethodName}($filters);
        $limit          = 50;
        $min            = $pageNumber == 1 ? 0 : ( $pageNumber - 1 ) * $limit;
        $max            = $pageNumber * $limit;
        $paginationHtml = "<ul style='display: flex;justify-content: center'>";
        $fPage          = $pageNumber + 1;
        $bPage          = $pageNumber - 1;
        if ($pageNumber > 1) {
            $paginationHtml .= "<li><a class='button' href='$currentUrl&page_number=$bPage'>صفحه قبل</a></li>";
        }
        if (( ( $count / $limit ) / $pageNumber ) > 1) {
            $paginationHtml .= "<li><a class='button' href='$currentUrl&page_number=$fPage'>صفحه بعد</a></li>";
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
