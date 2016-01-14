<?php

namespace Chayka\SEO;

use Chayka\Helpers\DateHelper;
use Chayka\Helpers\FsHelper;
use Chayka\Helpers\Util;
use Chayka\WP\Helpers\DbHelper;
use Chayka\WP\Models\PostModel;
use Chayka\WP\Models\TermModel;
use Chayka\WP\Models\UserModel;

class SitemapHelper{

    /**
     * Get initial content for robots.txt, in case if robots.txt is absent.
     *
     * @return string
     */
    public static function getRobotsContent(){
        return "User-Agent: *\nSitemap: " . Util::getAbsoluteUrl('/api/sitemap/');
    }

    /**
     * Check if robots.txt exists, create if not
     *
     * @return string
     */
    public static function ensureRobotsTxt(){
        $fn = self::getRobotsPath();

        if(!file_exists($fn)){
            $robots = self::getRobotsContent();
            file_put_contents($fn, $robots);
        }

        return $fn;
    }

    /**
     * Patch robots.txt to point sitemap to /api/sitemap/
     */
    public static function patchRobotsTxt(){
        $fn = self::getRobotsPath();
        if(!file_exists($fn)){
            self::ensureRobotsTxt();
        }

        $newRobots = $robots = file_get_contents($fn);

        $re = '/\bSitemap:[^\n]*/ims';
        if(!preg_match($re, $robots)){
            $newRobots.="\n".self::getRobotsContent();;
        }else{
            $newRobots = preg_replace($re, "Sitemap: " . Util::getAbsoluteUrl('/api/sitemap/'), $robots);
        }

        if($newRobots !== $robots){
            $backupFn = FsHelper::hideExtension($fn).date('.Y-m-d.H-i-s.').'txt';
            file_put_contents($backupFn, $robots);
            file_put_contents($fn, $newRobots);
        }
    }

    /**
     * Generate sitemap xml, save cache, return xml.
     *
     * @return string
     */
    public static function buildSitemap(){
        set_time_limit(0);
        SitemapHelper::ensureCacheDir();
        $indexFn = SitemapHelper::getSitemapIndexPath(true);
//        if(file_exists($indexFn)){
//            return file_get_contents($indexFn);
//        }

        $maxEntryPackSize = OptionHelper::getOption('maxEntryPackSize', 10);

        $barrels = [];

        $barrelPrimary = new \stdClass();
        $barrelPrimary->href = SitemapHelper::getSitemapPrimaryBarrelPath();
        $barrelPrimary->lastmod = new \DateTime();

        $barrelFn = SitemapHelper::getSitemapPrimaryBarrelPath(true);
        file_put_contents($barrelFn, SitemapHelper::renderPrimaryPackIndex());
        $barrels = array_merge($barrels, [$barrelPrimary]);

        $barrelsPosts = SitemapHelper::getPostsBarrels($maxEntryPackSize);

        foreach($barrelsPosts as $barrelData){
            $barrelFn = SitemapHelper::getSitemapPostsBarrelPath($barrelData->post_type, $barrelData->barrel, true);
            if(!file_exists($barrelFn)){
                file_put_contents($barrelFn, SitemapHelper::renderPostTypePackIndex($barrelData->post_type, $barrelData->barrel, $maxEntryPackSize));
            }
        }

        $barrels = array_merge($barrels, $barrelsPosts);

        $barrelsTaxonomies = SitemapHelper::getTaxonomiesBarrels($maxEntryPackSize);

        foreach($barrelsTaxonomies as $barrelData){
            $barrelFn = SitemapHelper::getSitemapTaxonomiesBarrelPath($barrelData->taxonomy, $barrelData->barrel, true);
            if(!file_exists($barrelFn)){
                file_put_contents($barrelFn, SitemapHelper::renderTaxonomyPackIndex($barrelData->taxonomy, $barrelData->barrel, $maxEntryPackSize));
            }
        }

        $barrels = array_merge($barrels, $barrelsTaxonomies);

        $usersEnabled = OptionHelper::getOption('sitemap_need_users', 1);
        if($usersEnabled){
            $usersBarrels = SitemapHelper::getUsersBarrels($maxEntryPackSize);
            foreach($usersBarrels as $barrelData){
                $barrelFn = SitemapHelper::getSitemapUsersBarrelPath($barrelData->barrel, true);
                if(!file_exists($barrelFn)){
                    file_put_contents($barrelFn, SitemapHelper::renderUsersPackIndex($barrelData->barrel, $maxEntryPackSize));
                }
            }
            $barrels = array_merge($barrels, $usersBarrels);
        }

        $sitemapIndex = SitemapHelper::renderSitemapIndex($barrels);

        file_put_contents($indexFn, $sitemapIndex);

        return $sitemapIndex;
    }

    /**
     * Drop whole sitemap cache
     */
    public static function flushSitemap(){
        $dir = self::getSitemapDir(true);
        if(is_dir(self::getSitemapDir(true))){
            FsHelper::delete($dir);
        }
    }

    /**
     * Get robots.txt path
     *
     * @return string
     */
    public static function getRobotsPath(){
        $fn = ABSPATH.'/robots.txt';

        if($_SERVER['DOCUMENT_ROOT']){
            $fn = $_SERVER['DOCUMENT_ROOT'].'/robots.txt';
        }

        return $fn;
    }

    /**
     * Create sitemap cache dir
     *
     * @return bool
     */
    public static function ensureCacheDir(){
        $dirSitemap = ABSPATH . '/wp-content/sitemap';
        $dirMsSitemap = $dirSitemap . '/' . Util::serverName();

        return (is_dir($dirSitemap) || mkdir($dirSitemap)) &&
               (is_dir($dirMsSitemap) || mkdir($dirMsSitemap));
    }

    /**
     * Get path for sitemap cache dir for the current site.
     * Needed to support multisite mode.
     *
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapDir($absPath = false){
        $path = '/wp-content/sitemap/' . Util::serverName() . '/';
        return $absPath ? ABSPATH . $path : $path;
    }

    /**
     * Get sitemap index file path
     *
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapIndexPath($absPath = false){
        return self::getSitemapDir($absPath) . 'index.xml';
    }

    /**
     * Get sitemap index file path
     *
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapPrimaryBarrelPath($absPath = false){
        return self::getSitemapDir($absPath) . 'primary.xml';
    }

    /**
     * Get path of a barrel that contains posts according to the specified post_type
     *
     * @param $postType
     * @param $barrel
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapPostsBarrelPath($postType, $barrel, $absPath = false){
        return self::getSitemapDir($absPath) . sprintf('posts.%s.%08x.xml', $postType, $barrel);
    }

    /**
     * Get path of a barrel that contains posts according to the specified post_type
     *
     * @param $taxonomy
     * @param $barrel
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapTaxonomiesBarrelPath($taxonomy, $barrel, $absPath = false){
        return self::getSitemapDir($absPath) . sprintf('terms.%s.%08x.xml', $taxonomy, $barrel);
    }

    /**
     * Get path of a barrel that contains users
     *
     * @param $barrel
     * @param bool|false $absPath
     *
     * @return string
     */
    public static function getSitemapUsersBarrelPath($barrel, $absPath = false){
        return self::getSitemapDir($absPath) . sprintf('users.%08x.xml', $barrel);
    }

    /**
     * Get post barrels data
     *
     * @param int $maxEntryPackSize
     *
     * @return array
     */
    public static function getPostsBarrels($maxEntryPackSize = 0){
        $table = PostModel::getDbTable();
        $maxEntryPackSize = $maxEntryPackSize ? $maxEntryPackSize :OptionHelper::getOption('maxEntryPackSize', 10);
        $postTypes = get_post_types(['public'=>true]); unset($postTypes['attachment']);
        $enabledPostTypes = [];
        foreach($postTypes as $postType){
            $enabled = OptionHelper::getOption('sitemap_need_type_' . $postType, 1);
            if($enabled || !strlen($enabled)){
                $enabledPostTypes[] = $postType;
            }
        }
        $types = "'" . join("', '", $enabledPostTypes) . "'";
        $sql = DbHelper::prepare("
        SELECT FLOOR(ID / %d) AS barrel, post_type, MAX(post_modified) as lastmod, COUNT(ID) AS count
        FROM $table
        WHERE post_type IN ($types)
        GROUP BY barrel, post_type
        ", $maxEntryPackSize);

        $barrels = DbHelper::selectSql($sql);

        foreach($barrels as $item){
            $item->lastmod = DateHelper::dbStrToDatetime($item->lastmod);
            $item->href = self::getSitemapPostsBarrelPath($item->post_type, $item->barrel, false);
        }

        return $barrels;
    }

    /**
     * Get post barrels data
     *
     * @param int $maxEntryPackSize
     *
     * @return array
     */
    public static function getTaxonomiesBarrels($maxEntryPackSize = 0){
        global $wpdb;
        $maxEntryPackSize = $maxEntryPackSize ? $maxEntryPackSize :OptionHelper::getOption('maxEntryPackSize', 10);
        $taxonomies = get_taxonomies(['public'=>true]);
        $enabledTaxonomies = [];
        foreach($taxonomies as $taxonomy){
            $enabled = OptionHelper::getOption('sitemap_need_taxonomy_' . $taxonomy, 1);
            if($enabled || !strlen($enabled)){
                $enabledTaxonomies[] = $taxonomy;
            }
        }
        $enTax = "'" . join("', '", $enabledTaxonomies) . "'";
        $t1 = $wpdb->term_taxonomy;
//        $t2 = $wpdb->terms;
        $sql = DbHelper::prepare("
        SELECT FLOOR(term_taxonomy_id / %d) AS barrel, taxonomy, COUNT(term_taxonomy_id) AS count
        FROM $t1
        WHERE taxonomy IN ($enTax) AND $t1.count > 0
        GROUP BY barrel, taxonomy
        ", $maxEntryPackSize);

        $barrels = DbHelper::selectSql($sql);

        $date = new \DateTime();

        foreach($barrels as $item){
            $item->lastmod = $date;
            $item->href = self::getSitemapTaxonomiesBarrelPath($item->taxonomy, $item->barrel, false);
        }

        return $barrels;
    }

    /**
     * Get post barrels data
     *
     * @param int $maxEntryPackSize
     *
     * @return array
     */
    public static function getUsersBarrels($maxEntryPackSize = 0){
        $table = UserModel::getDbTable();
        $maxEntryPackSize = $maxEntryPackSize ? $maxEntryPackSize :OptionHelper::getOption('maxEntryPackSize', 10);
        $sql = DbHelper::prepare("
        SELECT FLOOR(ID / %d) AS barrel, MAX(user_registered) as lastmod, COUNT(ID) AS count
        FROM $table
        WHERE spam = 0 AND deleted = 0
        GROUP BY barrel
        ", $maxEntryPackSize);

        $barrels = DbHelper::selectSql($sql);

        foreach($barrels as $item){
            $item->lastmod = DateHelper::dbStrToDatetime($item->lastmod);
            $item->href = self::getSitemapUsersBarrelPath($item->barrel, false);
        }

        return $barrels;
    }

    /**
     * Render custom paths calendar
     *
     * @return string
     */
    public static function renderPrimaryPackIndex(){
        $urls = [Util::getAbsoluteUrl('/') => true];

        $res = [];

        $navMenus = wp_get_nav_menus();

        foreach($navMenus as $navMenu){
            if($navMenu->count && OptionHelper::getOption('sitemap_need_nav_menu_' . $navMenu->slug)){
                $menuItems = wp_get_nav_menu_items($navMenu->term_id);
                foreach($menuItems as $menuItem){
                    $urls[Util::getAbsoluteUrl($menuItem->url)] = true;
                }
            }
        }

        $paths = OptionHelper::getOption('customSitemapPaths');
        if($paths){
            $paths = preg_split('/\s+/mU', $paths);
            foreach($paths as $path){
                $urls[Util::getAbsoluteUrl($path)] = true;
            }
        }

        foreach($urls as $url=>$enabled){
            $res[] = [
                'loc' => $url
            ];
        }
        $result = self::renderUrlSet($res);

        return $result;
    }

    /**
     * Render posts barrel separated by calendar
     *
     * @param $postType
     * @param int $year4d
     * @param int $month
     *
     * @return string
     */
    public static function renderPostTypeCalendarIndex($postType, $year4d = 0, $month = 0){
        $posts = [];
        $table = PostModel::getDbTable();
        $slq = DbHelper::prepare("
            SELECT ID, post_date, post_modified, post_title, post_name, post_excerpt, post_type
            FROM $table
            WHERE post_type = ? AND post_status = 'publish'", $postType);
        if($year4d){
            $sinceMonth = $month?$month:1;
            $tillMonth = $month?$month:12;
            $since = sprintf('%d-%02d-01', $year4d, $sinceMonth);
            $till = sprintf('%d-%02d-%02d', $year4d, $tillMonth, cal_days_in_month(CAL_GREGORIAN, $tillMonth, $year4d));

            $slq.= " AND post_date >= '$since' AND post_date <= '$till'";
        }

        if('page' == $postType){
            $posts[] = [
                'loc' => '/',
                'priority' => 1
            ];
        }

        $posts = array_merge($posts, PostModel::selectSql($slq));

        return $posts ? self::renderUrlSet($posts) : '';
    }

    /**
     * Render post type barrel sitemap
     *
     * @param $postType
     * @param bool|false $barrel
     * @param int $packSize
     *
     * @return string
     */
    public static function renderPostTypePackIndex($postType, $barrel = false, $packSize = 0){
        $table = PostModel::getDbTable();
        $sql = DbHelper::prepare("
            SELECT ID, post_date, post_modified, post_title, post_name, post_excerpt, post_type
            FROM $table
            WHERE post_type = %s AND post_status = 'publish'", $postType);
        if($barrel !== false){
            $sinceId = $barrel * $packSize;
            $tillId = $sinceId + $packSize;

            $sql.= sprintf(" AND ID >= %d AND ID < %d", $sinceId, $tillId);
        }

//        if('page' == $postType){
//            $posts[] = [
//                'loc' => '/',
//                'priority' => 1
//            ];
//        }

        $posts = PostModel::selectSql($sql);

        return $posts ? self::renderUrlSet($posts) : '';
    }

    /**
     * Render taxonomy barrel sitemap
     *
     * @param $taxonomy
     * @param bool|false $barrel
     * @param int $packSize
     *
     * @return string
     */
    public static function renderTaxonomyPackIndex($taxonomy, $barrel = false, $packSize = 0){
        $wpdb = DbHelper::wpdb();
        $t1 = $wpdb->term_taxonomy;
        $t2 = $wpdb->terms;
        $sql = $wpdb->prepare("
            SELECT *
            FROM $t1 LEFT JOIN $t2 USING(term_id)
            WHERE taxonomy = %s AND $t1.count > 0", $taxonomy);
        if($barrel !== false){
            $sinceId = $barrel * $packSize;
            $tillId = $sinceId + $packSize;

            $sql.= sprintf(" AND term_taxonomy_id >= %d AND term_taxonomy_id < %d", $sinceId, $tillId);
        }

        $terms = TermModel::selectSql($sql);
        return $terms ? self::renderUrlSet($terms) : '';
    }

    /**
     * Render users barrel sitemap
     *
     * @param bool|false $barrel
     * @param int $packSize
     *
     * @return string
     */
    public static function renderUsersPackIndex($barrel = false, $packSize = 0){
        $table = UserModel::getDbTable();
        $sql = "
            SELECT *
            FROM $table
            WHERE deleted = 0 AND spam = 0";
        if($barrel !== false){
            $sinceId = $barrel * $packSize;
            $tillId = $sinceId + $packSize;

            $sql.= sprintf(" AND ID >= %d AND ID < %d", $sinceId, $tillId);
        }

        $users = UserModel::selectSql($sql);

        return $users ? self::renderUrlSet($users) : '';
    }

    /**
     * @param PostModel[]|UserModel[]|TermModel[]|array $entries
     *
     * @return string
     */
    public static function renderUrlSet($entries){
        $view = Plugin::getView();
        $view->assign('entries', $entries);

        return $view->render('sitemap/urlset.phtml');
    }

    /**
     * @param array $sitemaps
     *
     * @return string
     */
    public static function renderSitemapIndex($sitemaps){
        $view = Plugin::getView();
        $view->assign('sitemaps', $sitemaps);

        return $view->render('sitemap/sitemapindex.phtml');
    }

}