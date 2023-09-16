<?php

class Helpers
{

    public static $version = "1.0.5";
    public static $author = "IDPay";
    public static $domain = "gravityformsIDPay";
    public static $min_gravityforms_version = "1.9.10";
    public const NO_PAYMENT = "NO_PAYMENT";
    public const SUCCESS_PAYMENT = "SUCCESS_PAYMENT";
    public const PLUGIN_FOLDER = "idpay-gravity-forms-plugin";
    public const KEY_IDPAY = "gf_IDPay_settings";

    public static function exists($array, $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        if (is_float($key)) {
            $key = (string) $key;
        }

        return array_key_exists($key, $array);
    }

    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    public static function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }

    public static function collapse($array): array
    {
        $results = [];

        foreach ($array as $values) {
            if (! is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    public static function dataGet($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[ $i ]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (! is_iterable($target)) {
                    return self::value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = self::dataGet($item, $key);
                }

                return in_array('*', $key) ? self::collapse($result) : $result;
            }

            if (self::accessible($target) && self::exists($target, $segment)) {
                $target = $target[ $segment ];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return self::value($default);
            }
        }

        return $target;
    }

    public static function Return_URL($form_id, $entry_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        if ($_SERVER['SERVER_PORT'] != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $arr_params = array( 'id', 'entry', 'no', 'Authority', 'Status' );
        $pageURL    = esc_url(remove_query_arg($arr_params, $pageURL));

        $pageURL = str_replace('#038;', '&', add_query_arg(array(
            'form_id' => $form_id,
            'entry'   => $entry_id
        ), $pageURL));

        return apply_filters(self::$author . '_IDPay_return_url', apply_filters(self::$author . '_gateway_return_url', $pageURL, $form_id, $entry_id, __CLASS__), $form_id, $entry_id, __CLASS__);
    }

    public static function redirect_confirmation($url, $ajax)
    {
        if (headers_sent() || $ajax) {
            $confirmation = "<script type=\"text/javascript\">" . apply_filters('gform_cdata_open', '') . " function gformRedirect(){document.location.href='$url';}";
            if (! $ajax) {
                $confirmation .= 'gformRedirect();';
            }
            $confirmation .= apply_filters('gform_cdata_close', '') . '</script>';
        } else {
            $confirmation = array( 'redirect' => $url );
        }

        return $confirmation;
    }

    public static function checkOneConfirmationExists($confirmation, $form, $entry, $ajax): bool
    {
        if (apply_filters(
            'gf_IDPay_request_return',
            apply_filters('gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax),
            $confirmation,
            $form,
            $entry,
            $ajax
        )) {
            return false;
        }

        return true;
    }

    public static function checkSubmittedForIDPay($formId): bool
    {
        if (RGForms::post("gform_submit") != $formId) {
            return false;
        }

        return true;
    }

    public static function checkFeedExists($form): bool
    {
        return ! empty(IDPayDB::getActiveFeed($form));
    }

    public static function getGatewayName(): string
    {
        $settings = Helpers::getGlobalKey(Helpers::KEY_IDPAY);

        return isset($settings['name']) ? $settings["name"] : __('IDPay', 'gravityformsIDPay');
    }

    public static function getFeed($form)
    {
        $feed = IDPayDB::getActiveFeed($form);

        return reset($feed);
    }

    public static function fixPrice($amount, $form, $entry): int
    {
        return GFPersian_Payments::amount($amount, 'IRR', $form, $entry);
    }

    public static function isNotApprovedPrice($amount): int
    {
        return empty($amount) || $amount > 500000000 || $amount < 1000;
    }

    public static function isNotApprovedGettingTransaction($entryId, $form_id)
    {

        $entry         = GFPersian_Payments::get_entry($entryId);
        $paymentMethod = self::dataGet($entry, 'payment_method');
        $condition1    = apply_filters('gf_gateway_IDPay_return', apply_filters('gf_gateway_verify_return', false));
        $condition2    = ( ! IDPayOperation::checkApprovedGravityFormVersion() ) || is_wp_error($entry);
        $condition3    = ( ! is_numeric((int) $form_id) ) || ( ! is_numeric((int) $entryId) );
        $condition4    = empty($paymentMethod) || $paymentMethod != 'IDPay';

        return $condition1 || $condition2 || $condition3 || $condition4;
    }

    public static function getApiKey()
    {
        $settings = Helpers::getGlobalKey(Helpers::KEY_IDPAY);
        $api_key  = $settings["api_key"] ?? '';

        return trim($api_key);
    }

    public static function getSandbox()
    {
        $settings = Helpers::getGlobalKey(Helpers::KEY_IDPAY);

        return $settings["sandbox"] ? "true" : "false";
    }

    public static function httpRequest($url, $data)
    {
        $args = [
            'body'    => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => self::getApiKey(),
                'X-SANDBOX'    => self::getSandbox(),
            ],
            'timeout' => 30,
        ];

        $number_of_connection_tries = 3;
        while ($number_of_connection_tries) {
            $response = wp_safe_remote_post($url, $args);
            if (is_wp_error($response)) {
                $number_of_connection_tries --;
            } else {
                break;
            }
        }

        return $response;
    }

    public static function checkSetPriceForForm($form, $formId)
    {
        $check = false;
        if (isset($form['fields'])) {
            $fields = self::dataGet($form, 'fields');
            foreach ($fields as $field) {
                if ($field['type'] == 'product') {
                    $check = true;
                }
            }

            return $check;
        } elseif (empty($formId)) {
            return true;
        }

        return false;
    }

    public static function updateConfigAndRedirectPage($feedId, $data)
    {
        $idpayConfig = apply_filters(self::$author . '_gform_gateway_save_config', $data);
        $idpayConfig = apply_filters(self::$author . '_gform_IDPay_save_config', $idpayConfig);
        $feedId      = IDPayDB::updateFeed(
            $feedId,
            self::dataGet($idpayConfig, 'form_id'),
            self::dataGet($idpayConfig, 'meta'),
        );
        if (! headers_sent()) {
            wp_redirect(admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true'));
        } else {
            echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" .
                 admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true') . "'; };</script>";
        }
        exit;
    }

    public static function setStylePage()
    {
        if (is_rtl()) {
            echo '<style type="text/css">table.gforms_form_settings th {text-align: right !important}</style>';
        }
        if (! defined('ABSPATH')) {
            exit;
        }
        $styles = [ 'jquery-ui-styles', 'gform_admin_IDPay', 'wp-pointer' ];
        wp_register_style('gform_admin_IDPay', GFCommon::get_base_url() . '/assets/css/dist/admin.css');
        wp_print_styles($styles);

        return true;
    }

    public static function readDataFromRequest($config)
    {
        return [
            "form_id" => absint(rgpost("IDPay_formId")),
            "meta"    => [
                "description"         => rgpost("IDPay_description"),
                "payment_description" => rgpost("IDPay_payment_description"),
                "payment_email"       => rgpost("IDPay_payment_email"),
                "payment_mobile"      => rgpost("IDPay_payment_mobile"),
                "payment_name"        => rgpost("IDPay_payment_name"),
                "confirmation"        => rgpost("IDPay_payment_confirmation"),
                "addon"               => [
                    "post_create"       => [
                        "success_payment" => (bool) rgpost("IDPay_addon_post_create_success_payment"),
                        "no_payment"      => false,
                    ],
                    "post_update"       => [
                        "success_payment" => (bool) rgpost("IDPay_addon_post_update_success_payment"),
                        "no_payment"      => false,
                    ],
                    "user_registration" => [
                        "success_payment" => (bool) rgpost("IDPay_addon_user_reg_success_payment"),
                        "no_payment"      => (bool) rgpost("IDPay_addon_user_reg_no_payment"),
                    ],
                ],
            ]
        ];
    }

    public static function makeUpdateMessageBar($oldFeedId)
    {
        $feedId       = (int) rgget('id') ?? $oldFeedId;
        $message      = __(" فید {$feedId} به روز شد . %sبازگشت به لیست%s . ", "gravityformsIDPay");
        $updatedLabel = sprintf($message, "<a href='?page=gf_IDPay'>", "</a>");
        echo '<div class="updated fade" style="padding:6px">' . $updatedLabel . '</div>';

        return true;
    }

    public static function getVal($form, $fieldName, $selectedValue)
    {

        $fields = null;
        if (! empty($form) && is_array($form['fields'])) {
            foreach ($form['fields'] as $item) {
                $condition1 = isset($item['inputs']) && is_array($item['inputs']);
                $condition2 = ! rgar($item, 'displayOnly');
                if ($condition1) {
                    foreach ($item['inputs'] as $input) {
                        $id       = self::dataGet($input, 'id');
                        $label    = GFCommon::get_label($item, $id);
                        $fields[] = [ 'id' => $id, 'label' => $label ];
                    }
                } elseif ($condition2) {
                    $id       = self::dataGet($item, 'id');
                    $label    = GFCommon::get_label($item);
                    $fields[] = [ 'id' => $id, 'label' => $label ];
                }
            }
        }

        if ($fields != null && is_array($fields)) {
            $str = "<select name='{$fieldName}' id='{$fieldName}'>";
            $str .= "<option value=''></option>";
            foreach ($fields as $field) {
                $id       = self::dataGet($field, 'id');
                $label    = esc_html(GFCommon::truncate_middle(self::dataGet($field, 'label'), 40));
                $selected = $id == $selectedValue ? "selected='selected'" : "";
                $str      .= "<option value='{$id}' {$selected} >{$label}</option>";
            }
            $str .= "</select>";

            return $str;
        }

        return '';
    }

    public static function generateFeedSelectForm($formId)
    {
        $gfAllForms             = RGFormsModel::get_forms();
        $visibleFieldFormSelect = rgget('id') || rgget('fid') ? 'style="display:none !important"' : '';
        $label                  = 'یک فرم انتخاب نمایید';
        $optionsForms           = "<option value=''>{$label}</option>";
        foreach ($gfAllForms as $current_form) {
            $title        = esc_html($current_form->title);
            $val          = absint($current_form->id);
            $isSelected   = absint($current_form->id) == $formId ? 'selected="selected"' : '';
            $optionsForms = $optionsForms . "<option value={$val} {$isSelected}>{$title}</option>";
        }

        return (object) [
            'options' => $optionsForms,
            'visible' => $visibleFieldFormSelect
        ];
    }

    public static function generateStatusBarMessage($formId)
    {
        $updateFeedLabel = __("فید به روز شد . %sبازگشت به لیست%s.", "gravityformsIDPay");
        $updatedFeed     = sprintf($updateFeedLabel, "<a href='?page=gf_IDPay'>", "</a>");
        $feedHtml        = '<div class="updated fade" style="padding:6px">' . $updatedFeed . '</div>';

        return $feedHtml;
    }

    public static function loadDictionary($feedId, $formName)
    {
        return (object) [
            'label1'             => translate("تنظیمات", self::$domain),
            'label2'             => sprintf(__("شناسه :  %s", self::$domain), $feedId),
            'label3'             => sprintf(__("فرم :  %s", self::$domain), $formName),
            'label4'             => translate("تنظیمات کلی", self::$domain),
            'label5'             => translate("انتخاب فرم", self::$domain),
            'label6'             => translate("یک فرم انتخاب نمایید", self::$domain),
            'label7'             => translate("فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", self::$domain),
            'label8'             => translate("User_Registration تنظیمات", self::$domain),
            'label9'             => translate(' اگر این فرم وظیفه ثبت نام کاربر تنها در صورت پرداخت موفق را دارد تیک بزنید', self::$domain),
            'label10'            => translate("توضیحات پرداخت", self::$domain),
            'label11'            => translate("توضیحاتی که میتوانید در داشبورد سایت آیدی ببینید . می توانید از", self::$domain),
            'label11_2'          => translate("{{form_title}}  و  {{form_id}}", self::$domain),
            'label11_3'          => translate("نیز برای نشانه گذاری استفاده کنید", self::$domain),
            'label12'            => translate("نام پرداخت کننده", self::$domain),
            'label13'            => translate("ایمیل پرداخت کننده", self::$domain),
            'label14'            => translate("توضیح تکمیلی", self::$domain),
            'label15'            => translate("تلفن همراه پرداخت کننده", self::$domain),
            'label16'            => translate("مدیریت افزودنی های گراویتی", self::$domain),
            'label17'            => translate("هر کدام از گزینه ها را تنها در صورتی تیک بزنید که", self::$domain),
            'label17_2'          => translate("شما از پلاگین های افزودنی گراویتی", self::$domain),
            'label17_4'          => translate(" استفاده می کنید و باید پس از بازگشت به سایت اجرا شوند", self::$domain),
            'label17_5'          => translate("پس از پرداخت موفق اجرا شود  Post Creation", self::$domain),
            'label17_6'          => translate("پس از پرداخت موفق اجرا شود  Post Update", self::$domain),
            'label17_7'          => translate("برای ثبت نام کاربران پس از پرداخت موفق اجرا شود  User Registeration", self::$domain),
            'label17_8'          => translate("برای ثبت نام کاربران در تمام وضعیت های پرداخت اجرا شود  User Registeration", self::$domain),
            'label18'            => translate("استفاده از تاییدیه های سفارشی", self::$domain),
            'label19'            => translate("این گزینه را در صورتی تیک بزنید که", self::$domain),
            'label19_2'          => translate("نمیخواهید از تاییدیه ثبت فرم پیش فرض آیدی پی استفاده کنید", self::$domain),
            'label19_3'          => translate("و از تاییدیه های سفارشی گراویتی استفاده می کنید", self::$domain),
            'label19_4'          => translate("توجه * : برای ترکیب تاییدیه های سفارشی با آیدی پی از متغیر", self::$domain),
            'label19_5'          => translate("idpay_payment_result", self::$domain),
            'label19_6'          => translate("استفاده کنید", self::$domain),
            'label21'            => translate("ذخیره تنظیمات", self::$domain),
            'label22'            => translate("فرم های IDPay", self::$domain),
            'label23'            => translate("اقدام دسته جمعی", self::$domain),
            'label24'            => translate("اقدامات دسته جمعی", self::$domain),
            'label25'            => translate("حذف", self::$domain),
            'label26'            => translate('تنظیمات IDPay', self::$domain),
            'label27'            => translate('وضعیت', self::$domain),
            'label28'            => translate(" آیدی فید", self::$domain),
            'label29'            => translate("نوع تراکنش", self::$domain),
            'label30'            => translate("فرم متصل به درگاه", self::$domain),
            'label32'            => translate("عملیات", self::$domain),
            'label33'            => translate("ویرایش فید", self::$domain),
            'label34'            => translate("حذف", self::$domain),
            'label35'            => translate("ویرایش فرم", self::$domain),
            'label36'            => translate("صندوق ورودی", self::$domain),
            'label37'            => translate("تراکنش ها", self::$domain),
            'label38'            => translate("افزودن فید جدید", self::$domain),
            'label39'            => translate("نمودار ها", self::$domain),
            'label40'            => translate("درگاه با موفقیت غیرفعال شد ", self::$domain),
            'label41'            => translate("تنظیمات ذخیره شدند", self::$domain),
            'label42'            => translate("تنظیمات IDPay", self::$domain),
            'label43'            => translate("فعالسازی", self::$domain),
            'label44'            => translate("بله", self::$domain),
            'label45'            => translate("عنوان", self::$domain),
            'label46'            => translate("API KEY", self::$domain),
            'label47'            => translate("آزمایشگاه", self::$domain),
            'label48'            => translate("بله", self::$domain),
            'label49'            => translate("ذخیره تنظیمات", self::$domain),
            'label50'            => translate("غیر فعالسازی افزونه دروازه پرداخت IDPay", self::$domain),
            'label51'            => translate("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد", self::$domain),
            'label52'            => translate("غیر فعال سازی درگاه", self::$domain),
            'label53'            => translate("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد .آیا همچنان مایل به غیر فعالسازی میباشید؟", self::$domain),
            'label54'            => translate("غیر فعال سازی درگاه", self::$domain),
            'label55'            => translate("شناسه", self::$domain),
            'label56'            => translate("شناسه فرم", self::$domain),
            'label57'            => translate("کد رهگیری", self::$domain),
            'label58'            => translate("مبلغ", self::$domain),
            'label59'            => translate("واحد", self::$domain),
            'label60'            => translate("وضعیت", self::$domain),
            'label61'            => translate("روش پرداخت", self::$domain),
            'label62'            => translate("صفحه ارجاع", self::$domain),
            'label63'            => translate("تاریخ ایجاد", self::$domain),
            'label64'            => translate("تاریخ بروزرسانی", self::$domain),
            'add'                => translate("افزودن جدید", self::$domain),
            'back'               => translate("بازگشت", self::$domain),
            'feedNotExists'      => translate("شما هیچ فید مشخصی با آیدی پی ندارید . با افزودن جدید یکی بسازید", self::$domain),
            'transNotExists'     => translate("شما هیچ تراکنشی در این فید نداشته اید", self::$domain),
            'haveToEnable'            => translate("برای شروع باید درگاه فعال باشد . به تنظیمات IDPay بروید .", self::$domain),
            'labelCountFeed'     => translate("مجموع تعداد فید ها : ", self::$domain),
            'labelCountTrans'    => translate("مجموع تعداد تراکنش ها : ", self::$domain),
            'labelRow'    => translate("ردیف", self::$domain),
            'labelSelectGravity' => translate("از فیلدهای موجود در فرم گراویتی یکی را انتخاب کنید", self::$domain),
            'labelNotSupprt'     => sprintf(__("درگاه IDPay نیاز به گرویتی فرم نسخه %s دارد. برای بروز رسانی هسته گرویتی فرم به %s مراجعه نمایید.", self::$domain), $feedId, $formName),
            'labelSettingsTab'    => translate("درگاه IDPay", self::$domain),
            'labelSettingsTitle'    => translate("تنظیمات درگاه IDPay", self::$domain),
            'labelPayment'    => translate("پرداخت امن با آیدی پی", self::$domain),
            'labelOn'    => translate("درگاه غیر فعال است", self::$domain),
            'labelOff'    => translate("درگاه غیر فعال است", self::$domain),
            'labelAjaxErr'    => translate("خطای Ajax رخ داده است", self::$domain),
            'labelDontPermission'    => translate("شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است .", self::$domain),
            'labelHintPersianGravity'    => translate("آیدی پی برای گرویتی فرم، نصب بسته فارسی ساز نسخه 2.3.1 به بالا الزامی است", self::$domain),
            'labelHintGravity'    => translate("آیدی پی به گرویتی فرم نسخه %s به بالا دارد.  به سایت گرویتی فرم مراجعه نمایید", self::$domain),
            'labelIdpay'    => translate("IDPay", self::$domain),
            'labelEmail'    => translate("ایمیل", self::$domain),
            'labelMobile'    => translate("موبایل", self::$domain),
            'labelDesc'    => translate("توضیحات", self::$domain),
            'labelTransactionData'    => translate("اطلاعات تراکنش آیدی پی:", self::$domain),
            'noStatus'    => translate("بدون وضعیت", self::$domain),
            'Paid'    => translate("پرداخت شده / موفق", self::$domain),
            'Failed'    => translate("لغو/کنسل یا پرداخت نشده", self::$domain),
            'Processing'    => translate("در انتظار پرداخت", self::$domain),
            'status'    => translate("وضعیت پرداخت : ", self::$domain),
            'date'    => translate("تاریخ پرداخت : ", self::$domain),
            'money'    => translate("مبلغ پرداختی : ", self::$domain),
            'currecny'    => translate("واحد پولی : ", self::$domain),
            'track'    => translate("کد رهگیری تراکنش: ", self::$domain),
            'ipg'    => translate("درگاه پرداخت : آیدی پی", self::$domain),
            'transId'    => translate("شناسه تراکنش : ", self::$domain),
            'time'    => translate("زمان پرداخت : ", self::$domain),
            'report'    => translate("اطلاعات تراکنش ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", self::$domain),
        ];
    }

    public static function checkSupportedGravityVersion()
    {
        $label1 = self::$min_gravityforms_version;
        $label2 = "<a href='http://gravityforms.ir' target='_blank'>سایت گرویتی فرم فارسی</a>";
        if (! IDPayOperation::checkApprovedGravityFormVersion()) {
            return self::loadDictionary($label1, $label2)->labelNotSupprt;
        }

        return true;
    }

    public static function getStatusFeedImage($setting)
    {
        $val1   = esc_url(GFCommon::get_base_url());
        $val2   = "/images/active";
        $val3   = intval($setting["is_active"]);
        $val4   = ".png";
        $image  = $val1 . $val2 . $val3 . $val4;
        $active = $setting["is_active"] ? "درگاه فعال است" : "درگاه غیر فعال است";

        return (object) [
            'image'  => $image,
            'active' => $active
        ];
    }

    public static function getTypeFeed($setting)
    {
        $label = " پرداخت/خرید ";
        $activeAddons = self::makeListDelayedAddons($setting);

        if (self::dataGet($activeAddons, 'postCreate')) {
            $label = $label . "+ ایجاد پست ";
        }
        if (self::dataGet($activeAddons, 'postUpdate')) {
            $label = $label . "+ آپدیت پست ";
        }
        if (self::dataGet($activeAddons, 'userRegistration')) {
            $label = $label . "+ ثبت نام کاربر ";
        }

        return $label;
    }

    public static function makeListDelayedAddons($config)
    {
        $config = self::dataGet($config, 'meta.addon');
        $postCreate = self::dataGet($config, 'post_create');
        $postUpdate = self::dataGet($config, 'post_update');
        $userRegistration = self::dataGet($config, 'user_registration');
        return [
            'postCreate' => $postCreate['success_payment'] || $postCreate['no_payment'],
            'postUpdate' => $postUpdate['success_payment'] || $postUpdate['no_payment'],
            'userRegistration' => $userRegistration['success_payment'] || $userRegistration['no_payment'],
        ];
    }

    public static function checkSubmittedOperation()
    {
        if (rgpost('action') == "delete") {
            check_admin_referer("list_action", "gf_IDPay_list");
            $id = absint(rgpost("action_argument"));
            IDPayDB::deleteFeed($id);

            return "<div class='updated fade' style='padding:6px'>فید حذف شد</div>";
        } elseif (! empty($_POST["bulk_action"])) {
            check_admin_referer("list_action", "gf_IDPay_list");
            $selected_feeds = rgpost("feed");
            if (is_array($selected_feeds)) {
                foreach ($selected_feeds as $feed_id) {
                    IDPayDB::deleteFeed($feed_id);
                }
                return "<div class='updated fade' style='padding:6px'>فید ها حذف شد</div>";
            }
        }

        return '';
    }

    public static function checkNeedToUpgradeVersion($setting)
    {
        $version = Helpers::dataGet($setting, 'version');
        return $version != self::$version ;
    }

    public static function checkSubmittedConfigDataAndLoadSetting()
    {
        $setting = Helpers::getGlobalKey(Helpers::KEY_IDPAY);

        if (isset($_POST["gf_IDPay_submit"])) {
            if (Helpers::checkNeedToUpgradeVersion($setting)) {
                IDPayDB::upgrade();
            }

            check_admin_referer("update", "gf_IDPay_update");
            $setting = [
                "enable"  => sanitize_text_field(rgpost('gf_IDPay_enable')),
                "name"   => sanitize_text_field(rgpost('gf_IDPay_name')),
                "api_key" => sanitize_text_field(rgpost('gf_IDPay_api_key')),
                "sandbox" => sanitize_text_field(rgpost('gf_IDPay_sandbox')),
                "version" => self::$version,
            ];
            Helpers::setGlobalKey(Helpers::KEY_IDPAY, $setting);
        }
        return $setting;
    }

    public static function loadConfigByEntry($entry)
    {
        $feed_id = gform_get_meta($entry["id"], "IDPay_feed_id");
        $feed    = ! empty($feed_id) ? IDPayDB::getFeed($feed_id) : '';
        $return  = ! empty($feed) ? $feed : false;

        return apply_filters(
            self::$author . '_gf_IDPay_get_config_by_entry',
            apply_filters(
                self::$author . '_gf_gateway_get_config_by_entry',
                $return,
                $entry
            ),
            $entry
        );
    }

    public static function loadConfig($entry, $form, $paymentType)
    {
        $config = null;

        if ($paymentType != 'custom') {
            $config = self::loadConfigByEntry($entry);
            if (empty($config)) {
                return null;
            }
        } elseif ($paymentType != 'form') {
            $config = apply_filters(
                self::$author . '_gf_IDPay_config',
                apply_filters(self::$author . '_gf_gateway_config', [], $form, $entry),
                $form,
                $entry
            );
        }

        return $config;
    }

    public static function loadUser()
    {
        global $current_user;
        $user_data = get_userdata($current_user->ID);
        $user_id   = 0;
        $user_name = '';
        if ($current_user && $user_data) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        } else {
            $user_name = __('مهمان', 'gravityformsIDPay');
            $user_id   = 0;
        }

        return (object) [
            'id'       => $user_id,
            'username' => $user_name
        ];
    }

    public static function getOrderTotal($form, $entry)
    {
        $total = GFCommon::get_order_total($form, $entry);
        $total = ( ! empty($total) && $total > 0 ) ? $total : 0;

        return apply_filters(
            self::$author . '_IDPay_get_order_total',
            apply_filters(self::$author . '_gateway_get_order_total', $total, $form, $entry),
            $form,
            $entry
        );
    }

    public static function getPriceOrder($paymentType, $entry, $form)
    {
        $entryId  = self::dataGet($entry, 'id');
        $formId   = self::dataGet($form, 'id');
        $currency = self::dataGet($entry, 'currency');

        if ($paymentType == 'custom') {
            $amount = gform_get_meta($entryId, 'IDPay_part_price_' . $formId);
        } else {
            $amount = self::getOrderTotal($form, $entry);
            $amount = GFPersian_Payments::amount($amount, 'IRR', $form, $entry);
        }

        return (object) [
            'amount' => $amount,
            'money'  => GFCommon::to_money($amount, $currency)
        ];
    }

    public static function checkTypeVerify()
    {
        return self::dataGet($_GET, 'no') == 'true' ? 'Free' : 'Purchase';
    }

    public static function getRequestData()
    {
        $method  = ! empty(rgpost('status')) ? 'POST' : 'GET';
        $keys    = [ 'status', 'track_id', 'id', 'order_id' ];
        $request = [];
        $all     = $method == 'POST' ? $_POST : $_GET;
        foreach ($keys as $key) {
            $value = $method == 'POST' ? rgpost($key) : rgget($key);
            if (empty($value)) {
                return null;
            }
            $request[ $key ] = $value;
        }

        return (object) [
            'id'      => self::dataGet($request, 'id'),
            'status'  => self::dataGet($request, 'status'),
            'trackId' => self::dataGet($request, 'track_id'),
            'orderId' => self::dataGet($request, 'order_id'),
            'formId'  => (int) self::dataGet($_GET, 'form_id'),
            'entryId' => (int) self::dataGet($_GET, 'entry'),
            'all'     => $all,
        ];
    }

    public static function checkApprovedVerifyData($request)
    {
        $keys = [ 'status', 'id', 'trackId', 'orderId', 'formId', 'entryId' ];
        foreach ($keys as $key) {
            if (self::dataGet($request, $key) == null) {
                return false;
            }
        }

        return true;
    }

    public static function getStatus($statusCode)
    {
        switch ($statusCode) {
            case 1:
                return 'پرداخت انجام نشده است';
            case 2:
                return 'پرداخت ناموفق بوده است';
            case 3:
                return 'خطا رخ داده است';
            case 4:
                return 'بلوکه شده';
            case 5:
                return 'برگشت به پرداخت کننده';
            case 6:
                return 'برگشت خورده سیستمی';
            case 7:
                return 'انصراف از پرداخت';
            case 8:
                return 'به درگاه پرداخت منتقل شد';
            case 10:
                return 'در انتظار تایید پرداخت';
            case 100:
                return 'پرداخت یا عضویت تایید شده است';
            case 101:
                return 'پرداخت یا عضویت قبلا تایید شده است';
            case 200:
                return 'به دریافت کننده واریز شد';
            case 0:
                return 'خطا در عملیات';
            default:
                return 'خطای ناشناخته';
        }
    }

    public static function isNotDoubleSpending($reference_id, $order_id, $transaction_id)
    {
        $relatedTransaction = gform_get_meta($reference_id, "IdpayTransactionId:$order_id", false);
        if (! empty($relatedTransaction)) {
            return $transaction_id == $relatedTransaction;
        }

        return false;
    }

    public static function processConfirmations(&$form, $request, $entry, $note, $status, $config)
    {
        $paymentType      = gform_get_meta($request->entryId, 'payment_type');
        $hasCustomPayment = ( $paymentType != 'custom' );
        $confirmPrepare   = apply_filters(self::$author . '_gf_gateway_verify', $hasCustomPayment, $form, $entry);
        $confirmations    = apply_filters(self::$author . '_gf_IDPay_verify', $confirmPrepare, $form, $entry);
        if ($confirmations) {
            foreach ($form['confirmations'] as $key => $value) {
                $message                                  = self::dataGet($value, 'message');
                $payment                                  = self::makeHtmlConfirmation($note, $status, $config, $message);
                $form['confirmations'][ $key ]['message'] = $payment;
            }
        }
    }

    public static function makeHtmlConfirmation($note, $status, $config, $message)
    {
        $key    = '{idpay_payment_result}';
        $type   = $status == 'Failed' ? 'Failed' : 'Success';
        $color  = $type == 'Failed' ? '#f44336' : '#4CAF50';
        $style  = "direction:rtl;padding: 20px;background-color:{$color};color: white;" .
                  "opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;";
        $output = "<div  style='{$style}'>{$note}</div>";

        return empty(self::dataGet($config, 'meta.confirmation')) ? $output : str_replace($key, $output, $message);
    }

    public static function checkTypePayment($amount): string
    {
        return empty($amount) || $amount == 0 ? 'Free' : 'Purchase';
    }


    public static function processAddons($form, $entry, $config, $type)
    {

        $config = self::dataGet($config, 'meta.addon');

        if ($type == self::NO_PAYMENT) {
            $ADDON_USER_REGESTERATION = (object) [
                'class' => 'GF_User_Registration',
                'run'   => self::dataGet($config, 'user_registration.no_payment') == true,
            ];

            self::runAddon($ADDON_USER_REGESTERATION, $form, $entry);
        }

        if ($type == self::SUCCESS_PAYMENT) {
            $ADDON_USER_REGESTERATION = (object) [
                'class' => 'GF_User_Registration',
                'run'   => self::dataGet($config, 'user_registration.success_payment') == true,
            ];

            $ADDON_POST_CREATION = (object) [
                'class' => 'GF_Advanced_Post_Creation',
                'run'   => self::dataGet($config, 'post_create.success_payment') == true,
            ];

            $ADDON_POST_UPDATE = (object) [
                'class' => 'ACGF_PostUpdateAddOn',
                'run'   => self::dataGet($config, 'post_update.success_payment') == true,
            ];

            self::runAddon($ADDON_USER_REGESTERATION, $form, $entry);
            self::runAddon($ADDON_POST_CREATION, $form, $entry);
            self::runAddon($ADDON_POST_UPDATE, $form, $entry);
        }
    }

    public static function runAddon($obj, $form, $entry)
    {
        $formId = self::dataGet($form, 'id');
        if ($obj->run == true) {
            $addon = call_user_func([ $obj->class, 'get_instance' ]);
            $feeds = $addon->getFeeds($formId);
            foreach ($feeds as $feed) {
                $addon->process_feed($feed, $entry, $form);
            }
        }
    }

    public static function prepareFrontEndTools()
    {
        include_once self::getBasePath() . '/resources/js/scripts.php';
        include_once self::getBasePath() . '/resources/css/styles.php';
    }

    public static function getBasePath()
    {
        $baseDir   = WP_PLUGIN_DIR;
        $pluginDir = self::PLUGIN_FOLDER;

        return "{$baseDir}/{$pluginDir}";
    }

    public static function calcFormId($fId, $config)
    {
        return ! empty($fId) ? $fId : ( ! empty($config) ? $config["form_id"] : null );
    }

    public static function makeStatusColor($status)
    {
        switch ($status) {
            case 'Processing':
                return "style='color: orange;'";
            case 'Paid':
                return "style='color: green;'";
            case 'Failed':
                return "style='color: red;'";
            default:
                return "style='color: black;'";
        }
    }

    public static function getJalaliDateTime($dateTime)
    {
        if (!empty($dateTime) && $dateTime != '-') {
            $jDateConvertor = JDate::getInstance();
            $y =  DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('Y');
            $m =   DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('m');
            $d =    DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('j');
            $time = DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('H:i:s');
            $jalaliDateTime =  $jDateConvertor->gregorian_to_persian($y, $m, $d);
            $jalaliY = (string) $jalaliDateTime[0] ;
            $jalaliM = (string) $jalaliDateTime[1] ;
            $jalaliD = (string) $jalaliDateTime[2] ;

            return "{$jalaliY}-{$jalaliM}-{$jalaliD} {$time}";
        }
        return '-';
    }

    public static function getMiladiDateTime($dateTime)
    {
        if (!empty($dateTime) && $dateTime != '-') {
            $jDateConvertor = JDate::getInstance();
            $y =  DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('Y');
            $m =   DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('m');
            $d =    DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('j');
            $time = DateTime::createFromFormat('Y-m-j H:i:s', $dateTime)->format('H:i:s');
            $jalaliDateTime =  $jDateConvertor->persian_to_gregorian($y, $m, $d);
            $jalaliY = (string) $jalaliDateTime[0] ;
            $jalaliM = (string) $jalaliDateTime[1] ;
            $jalaliD = (string) $jalaliDateTime[2] ;

            return "{$jalaliY}-{$jalaliM}-{$jalaliD} {$time}";
        }
        return '-';
    }

    public static function checkCurrentPageForIDPAY()
    {
        $condition = [ 'gf_IDPay', 'IDPay' ];
        $currentPage    = in_array(trim(rgget('page')), $condition);
        $currentView    = in_array(trim(rgget('view')), $condition);
        $currentSubview = in_array(trim(rgget('subview')), $condition);

        return $currentPage || $currentView || $currentSubview;
    }

    public static function checkEntryForIDPay($entry)
    {
        $payment_gateway = rgar($entry, "payment_method");
        return ! empty($payment_gateway) && $payment_gateway == "IDPay";
    }

    public static function getTypeEntryView()
    {
        $condition = ! strtolower(rgpost("save")) || RGForms::post("screen_mode") != "edit";
        return $condition == true ? 'showView' : 'editView';
    }

    public static function getGlobalKey($key)
    {
        $option = get_option($key);
        return !empty($option) ? $option : null;
    }

    public static function deleteGlobalKey($key)
    {
        delete_option($key);
    }

    public static function setGlobalKey($key, $value)
    {
        update_option($key, $value);
    }
}
