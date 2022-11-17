<?php
require_once('_Search/php/library/Common/Exception.php'); // Rsl_Logが入っています。
require_once('_Search/php/library/Util/Cache_m.php');
//ini_set('xdebug.show_local_vars', 0);
class Util_Abcookie {

    /**
     * クッキー発行設定 CSVファイル名
     */
    const CSV_MAIN_NAME = 'abtestList.csv';
    /**
     * クッキー発行設定 CSVファイル名 バックアップ（自動コピーなどはしていない）
     */
    const CSV_BACK_NAME = 'abtestList.csv.bak';

    /**
     *  CSVカラム abtest用キー テスト種別判別用キー
     */
    const CSV_COLMN_ABKEY               = 0;
    /**
     *  CSVカラム テスト適用From
     */
    const CSV_COLMN_DATE_FROM           = 1;
    /**
     *  CSVカラム テスト適用To
     */
    const CSV_COLMN_DATE_TO             = 2;
    /**
     *  CSVカラム テストクッキーにセットする最小alphabet
     */
    const CSV_COLMN_VALUE_MIN           = 3;
    /**
     *  CSVカラム テストクッキーにセットする最大小alphabet
     */
    const CSV_COLMN_VALUE_MAX           = 4;
    /**
     *  CSVカラム テストで使用するソートパラメーターファイル名
     */
    const CSV_COLMN_PATTERN_FILE        = 5;
    /**
     *  CSVカラム テストが有効になるパーセンテージ
     */
    const CSV_COLMN_PERCENT             = 6; // テスト試行割合（100分率）
    /**
     *  CSVカラム ソートパラメータ の種類 2014/07/04 'ad'：アジャスター仕様 / 'sc'： スコアラー仕様
     */
    const CSV_COLMN_CSV_TYPE            = 7;
    /**
     *  CSVカラム クッキーキー名 (DEFAULT> self::ABTEST_SESSION_KEY)
     */
    const CSV_COLMN_COOKIE_KEY          = 8;
    ///**
    // *  CSVカラム クッキー有効期間 (期間設定できるため未使用 セッションが続く限り有効になります)
    // */
    //const CSV_COLMN_COOKIE_DATE_LIMIT   = 9;
    /**
     *  CSVカラム 対象外時の値  (DEFAULT> ~PHP_INT_MAX)
     */
    const CSV_COLMN_NOT_COVERED         = 9;
    /**
     *  CSVカラム クッキー設定ドメイン (DEFAULT> ABTEST_SESSION_KEY_PATH:r.gnavi.co.jp)
     */
    const CSV_COLMN_COOKIE_DOMAIN       = 10;
    /**
     *  CSVカラム コメント（未使用） 見ません
     */
    const CSV_COLMN_COMMENT             = 11;
    /**
     *  CSVカラム オプション使用有無
     */
    const CSV_COLMN_USE_OPTION          = 12;

    /**
     * 確率導入時のカラム数
     */
    const COLUMN_COUNT_PERCENT          = 11;
    /**
     * ADJUSTER用CSVの時のカラム数
     */
    const COLUMN_COUNT_ADJUSTER         = 12;
    /**
     * COOKIEオプション設定用CSVの時のカラム数
     */
    const COLUMN_COUNT_OPTION           = 13;

    /**
     * パラメータCSVの種類 スコアラー
     */
    const CSV_TYPE_SCORE                = 'sc';
    /**
     * パラメータCSVの種類 アジャスター
     */
    const CSV_TYPE_ADJUSTER             = 'ad';

    // クッキーの固定値

    /**
     * クッキー ドメイン デフォルト
     */
    const ABTEST_SESSION_KEY_PATH = 'r.gnavi.co.jp'; // '/'なので使用されない
    /**
     * クッキー ドメイン 共有
     */
    const ABTEST_SESSION_KEY_PATH_DOMAIN = '.gnavi.co.jp';
    /**
     * クッキー キー デフォルト
     */
    const ABTEST_SESSION_KEY    = 'rsrchssn';
    /**
     * クッキー 使用可能な文字一覧  2014/07/04 'z'はテスト無効クッキーの値として使用するため対象外
     */
    const ALPHABET = 'abcdefghijklmnopqrstuvwxy';
    /**
     * クッキー パス デフォルト
     */
    const COOKIE_PATH = '/';

    /**
     * BasicSort用クッキーキー
     */
    const AB_COOKIE_KEY_BASICSORT = 'BS';

    /**
     * クッキーオプション使用有無 使用
     */
    const USE_OPTION_USE = 'use';
    /**
     * クッキーオプション使用有無 不使用
     */
    const USE_OPTION_NO_USE = 'no';


    // 引数

    /**
     * キー名なし
     */
    const KEY_NONE = 'NONE';

    /**
     * Date型最小値
     */
    const DATESTR_MIN = '1901-12-14 05:45:52';
    /**
     * Date型最大値
     */
    const DATESTR_MAX = '2038-01-19 12:14:07';

    /**
     * コンストラクタ
     */
    public function __construct() {
    }

    /**
     * A/Bテスト用CSV取得
     *
     * @param boolean $cache
     * @return array 整形されたCSVファイルの値
     *
     */
    public static function getCsv($cache)
    {
        static $localCsv=null;

        $cache = false;
        $csv = false;

        // APCu導入の際にはAPCuに保存？
        if (is_array($localCsv)) {
            //self::_csvLog($localCsv, 'read_static');
            return $localCsv;
        }

        if ($cache) {
            $ch = new Rsl_Util_Cache_m();
            //$cf = __METHOD__ . '_CSV'; // 共通の内容なのでabkeyはキーとして使用しない
            // 開発環境等では別のユーザーとキーが被るため、使用しているCSVパスで分ける。
            // 共通の内容なのでabkeyはキーとして使用しない
            $cf = Common_Rsl_Env::CACHE_FILE_PREFIX . __METHOD__ . '_CSV_' . Common_Rsl_Env::SORT_CSV_DIR;
            $csv = $ch->getData($cf);
        }
        if (!$csv) {

            // csvファイルの配置ディレクトリ
            if (defined('BASICSORT_CSV_DIR')) {
                $csv_path = BASICSORT_CSV_DIR . self::CSV_MAIN_NAME;
            } else {
                $csv_path = Common_Rsl_Env::SORT_CSV_DIR . self::CSV_MAIN_NAME;
            }

            // csvファイルの取得
            if (!file_exists($csv_path)){
                if (defined('BASICSORT_CSV_DIR')) {
                    $csv_path = BASICSORT_CSV_DIR . self::CSV_BACK_NAME;
                } else {
                    $csv_path = Common_Rsl_Env::SORT_CSV_DIR . self::CSV_BACK_NAME;
                }
                if (!file_exists($csv_path)){
                    Rsl_Log::write('バックアップcsv[' . self::CSV_BACK_NAME . ']が見つかりません', Rsl_Log::WARN);
                    return $csv;
                }
                Rsl_Log::write('バックアップcsv[' . self::CSV_BACK_NAME . ']を使用しています', Rsl_Log::WARN);
            }

            try {
                if (($fp = fopen($csv_path, "r")) !== FALSE) {
                    $csvArray = array();
                    while (($data = fgetcsv($fp, 1000, ",")) !== FALSE) {
                        $csvArray[$data[self::CSV_COLMN_ABKEY]][] = $data;
                    }
                    // CSV取得するときに期間で弾くと、キャッシュに反映されないため予約が効かなくなる
                    $csv = $csvArray;
                }
            } catch(Exception $exp) {
                Rsl_Log::write($exp->getMessage(), Rsl_Log::ERR);
            }

            if (!$csv) {
                $e = new Rsl_Exception('csvデータが取得出来ません');
                $e->log();
                throw $e;
            }
            if ($cache) {
                //self::_csvLog($csv, 'set_cachem[' . $cf .']');
                $ch->setData($cf, $csv);
            }
        //} else {
        //    self::_csvLog($csv, 'read_cachem[' . $cf .']');
        }
        //self::_csvLog($csv, 'set_static');
        $localCsv = $csv;

        return $csv;

    }
    //private static function _csvLog($csv, $source) {
    //    $csv_path = Common_Rsl_Env::SORT_CSV_DIR . 'csv_log';
    //    if (($fp = fopen($csv_path, "a")) !== FALSE) {
    //        $line = $source .'[' . date('Y/m/d').']' . '>>>' . json_encode($csv) . "\n";
    //        fwrite($fp, $line);
    //        fclose($fp);
    //    }
    //}

    /**
     * Csvチェック（期間外、不正な行を削除）
     *
     * @param array &$csvData 整形されたCSVファイル内容
     * @param string $csvType CSV種類の指定
     *
     */
    public static function cleanCsv(&$csvData, $csvType='')
    {
        if (!is_array($csvData)) {
            return;
        }

        // ※unsetではindexは振り直されない
        $csvBack = $csvData;
        foreach($csvBack as $csvKey => $csvDataArray) {
            if (!isset($csvDataArray[0])) {
                if (self::_checkCsv($csvDataArray, $csvType) === false) {
                    unset($csvData[$csvKey]);
                }
            } else {
                foreach($csvDataArray as $idx => $csv) {
                    if (self::_checkCsv($csv, $csvType) === false) {
                        unset($csvData[$csvKey][$idx]);
                        continue;
                    }
                }
                $csvData[$csvKey] = array_values($csvData[$csvKey]);
            }
            if (empty($csvData[$csvKey])) {
                unset($csvData[$csvKey]);
            }
        }
    }

    /**
     * A/Bテスト用のクッキーの設定値全てを取得する
     *
     * ※返却値について
     * BasicSortから移行していない場合、a-z 一文字が帰ります
     * 移行済みの場合、 クライアントのABテスト用に設定されているクッキーの配列
     * オプションで設定した値は取得しません
     *
     * @return mixed false/string/array
     *
     */
    public static function getAll()
    {
        $cookie = false;

        if (isset($_COOKIE[self::ABTEST_SESSION_KEY])) {
            $cookie = json_decode($_COOKIE[self::ABTEST_SESSION_KEY], true);;
        }

        return $cookie;

    }
    /**
     * A/Bテスト用のクッキーの設定値を有効なもののみに設定する
     *
     * CSVデータにないものは削除されます。
     *
     * @param array &$cookieArray クッキー配列
     * @param array $csv CSVデータ（$key=>$value整形済み）
     * @param string $domain ドメイン設定
     * @param string $cookieKey クッキーキー設定 default:self::ABTEST_SESSION_KEY 'rsrchssn'
     *
     */
    public static function clearSet($cookieArray, $csv, $domain='', $cookieKey=self::ABTEST_SESSION_KEY)
    {

        $okie = $cookieArray;

        $setCsv = $csv;

        self::cleanCsv($setCsv); // 期間外も弾くように

        $idx = 0;
        foreach($cookieArray as $name => $value) {
            if(!isset($setCsv[$name])) {
                unset($okie[$name]);
            }
        }

        $serialized = json_encode($okie);
        if (!empty($domain)) {
            setcookie($cookieKey,  $serialized, 0, self::COOKIE_PATH, $domain);
        } else {
            setcookie($cookieKey,  $serialized, 0, self::COOKIE_PATH);
        }

        $_COOKIE[$cookieKey] = $serialized;

    }

    /**
     * A/Bテスト用のクッキーの設定値を取得、設定する
     *
     * @param string $abkey キー
     * @param boolean $cache キャッシュ使用有無フラグ
     * @param string $domain ドメイン
     * @param type パラメータセットタイプ ('ad', 'sc')
     * @param type パラメータセットタイプ ('ad', 'sc')
     *
     * @return mixed false/a-z一文字（設定による）
     *
     */
    public static function get($abKey, $cache=true, $domain='', $type='', $notCoverd='')
    {
        static $localCache=array();

        $result = false;

        if (empty($abKey)) {
            return $result;
        }

        if (isset($localCache[$abKey])) {
            return $localCache[$abKey];
        }
        $csv = self::getCsv($cache);
        self::cleanCsv($csv, $type); // 期間外も弾くように

        // 期間外を削除するようになったため、キーが無い場合が有る。
        if (!isset($csv[$abKey]) || empty($csv[$abKey])) {
            return $result;
        }

        // sessionクッキーからどのセットを使用するか取得する
        //$cookiePath = '/';
        $cookieArray = array();
        if (count($csv[$abKey][0]) >= self::COLUMN_COUNT_PERCENT) {
            $valuePercent = (int)$csv[$abKey][0][self::CSV_COLMN_PERCENT];
        } else {
            $valuePercent = 100;
        }
        $valueMin = $csv[$abKey][0][self::CSV_COLMN_VALUE_MIN];
        $valueMax = $csv[$abKey][0][self::CSV_COLMN_VALUE_MAX];

        if (
                (isset($csv[$abKey][0][self::CSV_COLMN_USE_OPTION]))
            &&  ($csv[$abKey][0][self::CSV_COLMN_USE_OPTION] === self::USE_OPTION_USE)
            ) {
            $cookieKey = $csv[$abKey][0][self::CSV_COLMN_COOKIE_KEY];
            if (empty($domain)) {
                $domain = $csv[$abKey][0][self::CSV_COLMN_COOKIE_DOMAIN];
            }
            if (empty($notCoverd)) {
                $notCoverd = $csv[$abKey][0][self::CSV_COLMN_NOT_COVERED];
            }
        } else {
            $cookieKey  = self::ABTEST_SESSION_KEY;
            $notCoverd = '';
        }

        if (isset($_COOKIE[$cookieKey])) {
            $cookie = json_decode($_COOKIE[$cookieKey], true);
            if (is_array($cookie)) {

                $cookieArray = $cookie;
                if (
                       (isset($cookie[$abKey]))
                    && (!empty($cookie[$abKey]))
                    && (
                          (strpos(self::ALPHABET, $valueMin) <= strpos(self::ALPHABET, $cookie[$abKey]))
                        &&(strpos(self::ALPHABET, $cookie[$abKey]) <= strpos(self::ALPHABET, $valueMax))
                       )
                    ) {

                    $result = strtolower($cookieArray[$abKey]);
                } else {
                    // 検索時のパフォーマンスのため整形して保存
                    $cookieArray[$abKey] = self::_getValue($valueMin, $valueMax, $valuePercent, $notCoverd);
                    $result = $cookieArray[$abKey];
                }

            } else {
                // Basicsort $cookieはNULLになることに注意
                $cookieArray[self::AB_COOKIE_KEY_BASICSORT] = strtolower($_COOKIE[$cookieKey]);
                $result = $cookieArray[self::AB_COOKIE_KEY_BASICSORT];
            }
        } else {
            $cookieArray[$abKey] = self::_getValue($valueMin, $valueMax, $valuePercent, $notCoverd);
            $result = $cookieArray[$abKey];
        }

        // adjusterとそれ以外で双方セットしないといけないため、とりなおす。
        $csv = self::getCsv($cache);

        self::clearSet($cookieArray, $csv, $domain, $cookieKey);

        $localCache[$abKey] = $result;

        return $result;
    }

    /**
     * A/Bテスト用値振り出し
     *
     * @param string $valueMin a-zまたは数値 振り出しalphabetまたは数値 最小値
     * @param string $valueMax a-zまたは数値 振り出しalphabetまたは数値 最大値
     * @param integer $percentage 1-100 対象になるパーセンテージ
     * @param string $notCoverd 対象外の時にセットされる値
     *
     */
    private static function _getValue($valueMin, $valueMax, $percentage, $notCoverd)
    {
        // zは判定外の値として予約済み
        if (($percentage != 100) && (mt_rand(1,100) > $percentage)) {
            if (isset($notCoverd) && !empty($notCoverd)) {
                return $notCoverd;
            } else {
                if ((is_numeric($valueMin)) && (is_numeric($valueMax))) {
                    return (string)~PHP_INT_MAX;
                } else {
                    return 'z';
                }
            }
        }

        if ((is_numeric($valueMin)) && (is_numeric($valueMax))) {
            $num = mt_rand((int)$valueMin, (int)$valueMax);
            return (string)$num;
        } else {
            $num = mt_rand(strpos(self::ALPHABET, $valueMin), strpos(self::ALPHABET, $valueMax));
            $abc = self::ALPHABET;
            return $abc[$num];
        }

    }

    /**
     * CSVデータ一行分チェック
     *
     * @param array $csvData CSV一行分のデータ
     * @param string $csvType CSV種類の指定
     *
     */
    private static function _checkCsv($csvData, $csvType='')
    {

        date_default_timezone_set('Asia/Tokyo');

        $strFrom    = $csvData[self::CSV_COLMN_DATE_FROM];
        if ($strFrom === self::KEY_NONE) {
            $strFrom = self::DATESTR_MIN;
        }
        $fromDate   = strtotime($strFrom);
        if ($fromDate === false) {
            return false;
        }

        $strTo  = $csvData[self::CSV_COLMN_DATE_TO];
        if ($strTo === self::KEY_NONE) {
            $strTo = self::DATESTR_MAX;
        }
        $toDate     = strtotime($strTo);
        if ($toDate === false) {
            return false;
        }

        $nowDate    = time();
        if (
            ($fromDate > $nowDate)
        ||  ($toDate <= $nowDate)
            ) {
            return false;
        }

        $valueMin = strtolower($csvData[self::CSV_COLMN_VALUE_MIN]);
        $valueMax = strtolower($csvData[self::CSV_COLMN_VALUE_MAX]);
        if (
                (is_numeric($valueMin))
            &&  (is_numeric($valueMax))
            ) {
            if ((int)$valueMin > (int)$valueMax) {
                return false;
            }
        } else {
            if (
                (!ctype_alpha($valueMin))
            ||  (strlen($valueMin) != 1)
            ||  (strpos(self::ALPHABET, $valueMin) === FALSE)
                ) {
                return false;
            }
            if (
                (!ctype_alpha($valueMax))
            ||  (strlen($valueMax) != 1)
            ||  (strpos(self::ALPHABET, $valueMax) === FALSE)
                ) {
                return false;
            }
            if (strpos(self::ALPHABET, $valueMax) < strpos(self::ALPHABET, $valueMin)) {
                return false;
            }
        }

        $colCount = count($csvData);
        if ($colCount >= self::COLUMN_COUNT_PERCENT) {
            $percentage    = (int)$csvData[self::CSV_COLMN_PERCENT];
            if (!is_int($percentage) || (0 >= $percentage) || ($percentage > 100)) {
                return false;
            }
        }

        if ($colCount >= self::COLUMN_COUNT_OPTION) {
            $optionUse = $csvData[self::CSV_COLMN_USE_OPTION];
            if (($optionUse !== self::USE_OPTION_USE) && ($optionUse !== self::USE_OPTION_NO_USE)) {
                return false;
            }
        }

        if (!empty($csvType)) {
            if ($colCount >= self::COLUMN_COUNT_ADJUSTER) {
                $type = $csvData[self::CSV_COLMN_CSV_TYPE];
                if ($type != $csvType) {
                    return false;
                }
            //} else {
            //    // typeカラムがないので
            //    return false;
            }
        //} else {
        //    if ($colCount >= self::COLUMN_COUNT_ADJUSTER) {
        //        return false;
        //    }
        }

        return true;

    }

} 
