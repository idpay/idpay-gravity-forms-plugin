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



    public static function prepareQuery($type, $filters)
    {
        $transactionTableName = self::getTransactionTableName();
        $formTable    = RGFormsModel::get_form_table_name();
        $IDPayTable = self::getTableName();

        switch ($type) {
            case self::QUERY_ANALYTICS:
                return " SELECT status, sum(payment_amount) revenue, count(id) transactions 
						                            FROM {$transactionTableName}
												    WHERE form_id={$filters->formId} 
										              AND status='active' 
										              AND is_fulfilled=1 
										              AND payment_method='IDPay'
										           GROUP BY status";

            case self::QUERY_COUNT_FEED:
                return " SELECT count(*) as count 
 								   					FROM {$IDPayTable}";

            case self::QUERY_FEED_BY_ID:
                return "SELECT * 
												  FROM {$IDPayTable} 
												  WHERE form_id={$filters->formId}";

            case self::QUERY_DELETE_FEED:
                return "DELETE FROM {$IDPayTable} WHERE id={$filters->id}";

            case self::QUERY_FEED:
                return "SELECT id, form_id, is_active, meta FROM {$IDPayTable} WHERE id={$filters->id}";

            case self::QUERY_FEEDS:
                return "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
							                       FROM (
							                             SELECT * 
							                             FROM {$IDPayTable} 
							                                 LIMIT {$filters->limitRowsMin},{$filters->limitRowsMax} 
							                           ) s
							                       INNER JOIN {$formTable} f 
							                           ON s.form_id = f.id  
												   ORDER BY s.id";

            case self::QUERY_FORM:
                return "SELECT title as form_title FROM {$formTable} WHERE id={$filters->formId}";

            case self::QUERY_DELETE_IDPAY:
                return "DROP TABLE IF EXISTS {$IDPayTable}";

            case self::QUERY_COUNT_TRANSACTION:
                return " SELECT count(*) as count 
						                                   FROM {$transactionTableName} 
						                                   WHERE form_id={$filters->formId} 
							                               AND payment_method='IDPay'";


            case self::QUERY_TRANSACTIONS:
                return " SELECT *****  
 						 FROM {$transactionTableName} ";
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
        $queryShowTable   = "SHOW TABLES LIKE '{$oldTable}'";
        $queryRenameTable = "ALTER TABLE {$oldTable} RENAME {$tableName}";
        $existsTable      = ! empty($wpdb->get_var($queryShowTable));
        $charset          = ! empty($wpdb->charset) ? $wpdb->charset : null;
        $collate          = ! empty($wpdb->collate) ? $wpdb->collate : null;
        $options          = ! empty($charset) ? "DEFAULT CHARACTER SET {$charset}" : "";
        $options          .= ! empty($collate) ? " COLLATE {$collate}" : "";

        $queryCreateTable = "CREATE TABLE IF NOT EXISTS {$tableName} (
              id mediumint(8) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              is_active tinyint(1) not null default 1,
              meta longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
		) {$options};";


        if ($existsTable == true) {
            $wpdb->query($queryRenameTable);

            return 'completed rename table';
        } else {
            dbDelta($queryCreateTable);

            return 'completed create table';
        }
    }

    public static function getFeeds($pagination)
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
        $type  = self::QUERY_FORM;
        $filters = (object)[ 'formId' => $formId];
        $query = self::prepareQuery($type, $filters);
        $results =  self::runQuery($query, $type);
        return $results[0];
    }

    public static function getTransactions($pagination)
    {
		return [];
    }

    public static function getWithPaginate($methodName, $filters)
    {
        $pagination = self::loadPagination($methodName, $filters);
        $data = self::loadData($methodName, $pagination);

        return (object) [
            'query' => (object) [
                'min'   => $pagination->query->min,
                'max'   => $pagination->query->max,
                'count'   => $pagination->query->count,
            ],
            'data' => $data,
            'meta'  => $pagination->html,
        ];
    }

    public static function loadData($methodName, $pagination)
    {
        return self::{$methodName}($pagination);
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
