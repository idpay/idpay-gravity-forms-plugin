<?php

class IDPayDB
{
	public static $author = "IDPay";
    private static $method = 'IDPay';

    public static function update_table()
    {

        global $wpdb;

        $table_name = self::getTableName();

        $old_table = $wpdb->prefix . "rg_IDPay";
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'")) {
            $wpdb->query("RENAME TABLE $old_table TO $table_name");
        }

        $charset_collate = '';
        if (!empty($wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }
        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }

        $feed = "CREATE TABLE IF NOT EXISTS $table_name (
              id mediumint(8) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              is_active tinyint(1) not null default 1,
              meta longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
		) $charset_collate;";

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        dbDelta($feed);
    }

    public static function get_entry_table_name()
    {

        $version = GFCommon::$version;
        if (method_exists('GFFormsModel', 'get_database_version')) {
            $version = GFFormsModel::get_database_version();
        }

        return version_compare($version, '2.3-dev-1', '<') ? GFFormsModel::get_lead_table_name() : GFFormsModel::get_entry_table_name();
    }

    public static function drop_tables()
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::getTableName());
    }

    public static function get_available_forms()
    {
        $forms = RGFormsModel::get_forms();
        $available_forms = array();
        foreach ($forms as $form) {
            $available_forms[] = $form;
        }

        return $available_forms;
    }

    public static function get_feed($id)
    {
        global $wpdb;
        $table_name = self::getTableName();
        $sql = $wpdb->prepare("SELECT id, form_id, is_active, meta FROM $table_name WHERE id=%d", $id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (empty($results)) {
            return array();
        }
        $result = $results[0];
        $result["meta"] = maybe_unserialize($result["meta"]);

        return $result;
    }

    public static function get_feeds()
    {
        global $wpdb;
        $table_name = self::getTableName();
        $form_table_name = RGFormsModel::get_form_table_name();
        $sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                FROM $table_name s
                INNER JOIN $form_table_name f ON s.form_id = f.id";
        $results = $wpdb->get_results($sql, ARRAY_A);
        $count = sizeof($results);
        for ($i = 0; $i < $count; $i++) {
            $results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
        }

        return $results;
    }


    public static function get_transaction_totals($form_id)
    {
        global $wpdb;
        $entry_table_name = self::get_entry_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$entry_table_name} l
                                 WHERE l.form_id=%d AND l.status=%s AND l.is_fulfilled=%d AND l.payment_method=%s
                                 GROUP BY l.status", $form_id, 'active', 1, self::$method);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if (is_array($results)) {
            foreach ($results as $result) {
                $totals[$result["status"]] = array(
                    "revenue" => empty($result["revenue"]) ? 0 : $result["revenue"],
                    "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]
                );
            }
        }

        return $totals;
    }

    public static function get_transaction_totals_this_gateway()
    {
        global $wpdb;
        $entry_table_name = self::get_entry_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$entry_table_name} l
                                 WHERE l.status=%s AND l.is_fulfilled=%d AND l.payment_method=%s
                                 GROUP BY l.status", 'active', 1, self::$method);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if (is_array($results)) {
            foreach ($results as $result) {
                $totals[$result["status"]] = array(
                    "revenue" => empty($result["revenue"]) ? 0 : $result["revenue"],
                    "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]
                );
            }
        }

        return $totals;
    }

    public static function get_transaction_totals_gateways($form_id)
    {
        global $wpdb;
        $entry_table_name = self::get_entry_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$entry_table_name} l
                                 WHERE l.form_id=%d AND l.status=%s AND l.is_fulfilled=%d
                                 GROUP BY l.status", $form_id, 'active', 1);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if (is_array($results)) {
            foreach ($results as $result) {
                $totals[$result["status"]] = array(
                    "revenue" => empty($result["revenue"]) ? 0 : $result["revenue"],
                    "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]
                );
            }
        }

        return $totals;
    }

    public static function get_transaction_totals_site()
    {
        global $wpdb;
        $entry_table_name = self::get_entry_table_name();
        $sql = $wpdb->prepare(" SELECT l.status, sum(l.payment_amount) revenue, count(l.id) transactions
                                 FROM {$entry_table_name} l
                                 WHERE l.status=%s AND l.is_fulfilled=%d
                                 GROUP BY l.status", 'active', 1);
        $results = $wpdb->get_results($sql, ARRAY_A);
        $totals = array();
        if (is_array($results)) {
            foreach ($results as $result) {
                $totals[$result["status"]] = array(
                    "revenue" => empty($result["revenue"]) ? 0 : $result["revenue"],
                    "transactions" => empty($result["transactions"]) ? 0 : $result["transactions"]
                );
            }
        }

        return $totals;
    }

    /* ------------------ New Section And Refactored Functions ****************** */

    public static function getActiveFeed($form)
    {
        $configs = IDPayDB::getFeedByFormId( $form["id"] );
        $configs = apply_filters(
            self::$author . '_gf_IDPay_get_active_configs',
            apply_filters(self::$author . '_gf_gateway_get_active_configs', $configs, $form),
            $form
        );
        return $configs;
    }

	public static function getFeedByFormId( $formId )
	{
		global $wpdb;
		$tableName = self::getTableName();
		$query = "SELECT * FROM {$tableName} WHERE form_id={$formId}";
		$results = $wpdb->get_results($query, ARRAY_A);
		if (empty($results)) {
			return [];
		}
		$count = sizeof($results);
		for ($i = 0; $i < $count; $i++) {
			$results[$i]["meta"] = maybe_unserialize($results[$i]["meta"]);
		}

		return $results;
	}

	public static function getTableName()
	{
		global $wpdb;
		return $wpdb->prefix . "gf_IDPay";
	}

	public static function updateFeed($id, $form_id, $setting)
	{
		global $wpdb;
		$dto = [
			"form_id" => $form_id,
			"is_active" => true,
			"meta" => maybe_serialize($setting)
		];

		if ($id == 0) {
			$wpdb->insert(self::getTableName(),$dto, ["%d", "%d", "%s"]);
			$id = $wpdb->get_var("SELECT LAST_INSERT_ID()");
		} else {
			$wpdb->update(self::getTableName(), $dto, ["id" => $id], ["%d", "%d", "%s"], ["%d"]);
		}

		return $id;
	}

	public static function deleteFeed($id)
	{
		global $wpdb;
		$table_name = self::getTableName();
		$query = "DELETE FROM {$table_name} WHERE id={$id}";
		$wpdb->query($query);
	}
	
	
}
