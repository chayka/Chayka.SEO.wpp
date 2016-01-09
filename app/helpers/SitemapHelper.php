<?php

namespace Chayka\SEO;

use Chayka\Helpers\DateHelper;
use Chayka\Helpers\FsHelper;
use Chayka\Helpers\Util;
use Chayka\WP\Helpers\DbHelper;
use Chayka\WP\Models\PostModel;

class SitemapHelper{

    public static function getRobotsContent(){
        return "User-Agent: *\nSitemap: " . Util::getAbsoluteUrl('/api/sitemap/');
    }

    public static function ensureRobotsTxt(){
        $fn = self::getRobotsPath();

        if(!file_exists($fn)){
            $robots = self::getRobotsContent();
            file_put_contents($fn, $robots);
        }

        return $fn;
    }

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

    public static function buildSitemap(){
        set_time_limit(0);
        SitemapHelper::ensureCacheDir();
        $indexFn = SitemapHelper::getSitemapIndexPath(true);

        if(file_exists($indexFn)){
            return file_get_contents($indexFn);
        }

        $maxEntryPackSize = OptionHelper::getOption('maxEntryPackSize', 10);

        $barrels = SitemapHelper::getBarrels($maxEntryPackSize);

        foreach($barrels as $barrelData){
            $barrelFn = SitemapHelper::getSitemapBarrelPath($barrelData->post_type, $barrelData->barrel, true);
            if(!file_exists($barrelFn)){
                file_put_contents($barrelFn, SitemapHelper::renderPostTypePackIndex($barrelData->post_type, $barrelData->barrel, $maxEntryPackSize));
            }
        }

        $sitemapIndex = SitemapHelper::renderSitemapIndex($barrels);

        file_put_contents($indexFn, $sitemapIndex);

        return $sitemapIndex;
    }

    public static function flushSitemap(){
        $dir = self::getSitemapDir(true);
        if(is_dir(self::getSitemapDir(true))){
            FsHelper::delete($dir);
        }
    }

    public static function getRobotsPath(){
        $fn = ABSPATH.'/robots.txt';

        if($_SERVER['DOCUMENT_ROOT']){
            $fn = $_SERVER['DOCUMENT_ROOT'].'/robots.txt';
        }

        return $fn;
    }

    public static function ensureCacheDir(){
        $dirSitemap = ABSPATH . '/wp-content/sitemap';
        $dirMsSitemap = $dirSitemap . '/' . Util::serverName();

        return (is_dir($dirSitemap) || mkdir($dirSitemap)) &&
               (is_dir($dirMsSitemap) || mkdir($dirMsSitemap));
    }

    public static function getSitemapDir($absPath = false){
        $path = '/wp-content/sitemap/' . Util::serverName() . '/';
        return $absPath ? ABSPATH . $path : $path;
    }

    public static function getSitemapIndexPath($absPath = false){
        return self::getSitemapDir($absPath) . 'index.xml';
    }

    public static function getSitemapBarrelPath($postType, $barrel, $absPath = false){
        return self::getSitemapDir($absPath) . sprintf('%s.%08x.xml', $postType, $barrel);
    }

    public static function getSitemapUrlsPath($absPath = false){
        return self::getSitemapDir($absPath) . 'urls.xml';
    }

    public static function getBarrels($maxEntryPackSize = 0){
        $table = PostModel::getDbTable();
        $maxEntryPackSize = $maxEntryPackSize ? $maxEntryPackSize :OptionHelper::getOption('maxEntryPackSize', 10);
        $postTypes = get_post_types(['public'=>true]); unset($postTypes['attachment']);
        $enabledPostTypes = [];
        foreach($postTypes as $postType){
            $enabled = OptionHelper::getOption('sitemap_need_type_' . $postType);
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
            $item->href = self::getSitemapBarrelPath($item->post_type, $item->barrel, false);
        }

        return $barrels;
    }

    public static function renderIndex($isCache = false, $forceRefresh = false){
        $indexPath = ABSPATH.'sitemap.xml';
//        Util::print_r($data);
//        $data =
//        echo self::renderSitemapIndex($data);
    }

    public static function renderCustomPathsIndex(){
        $paths = OptionHelper::getOption('customSitemapPaths');
        $result = '';
        if($paths){
            $paths = preg_split('/\s+/mU', $paths);
            $urls = [];
            foreach($paths as $path){
                $urls[] = [
                    'loc' => $path
                ];
            }
            $result = self::renderUrlSet($urls);
        }

        return $result;
    }

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

    public static function renderPostTypePackIndex($postType, $barrel = false, $packSize = 0){
        $posts = [];
        $table = PostModel::getDbTable();
        $slq = DbHelper::prepare("
            SELECT ID, post_date, post_modified, post_title, post_name, post_excerpt, post_type
            FROM $table
            WHERE post_type = %s AND post_status = 'publish'", $postType);
        if($barrel !== false){
            $sinceId = $barrel * $packSize;
            $tillId = $sinceId + $packSize;

            $slq.= sprintf(" AND ID >= %d AND ID < %d", $sinceId, $tillId);
        }

//        if('page' == $postType){
//            $posts[] = [
//                'loc' => '/',
//                'priority' => 1
//            ];
//        }

        $posts = PostModel::selectSql($slq);

        return $posts ? self::renderUrlSet($posts) : '';
    }

    /**
     * @param \Chayka\WP\Models\PostModel[]|array $posts
     *
     * @return string
     */
    public static function renderUrlSet($posts){
        $view = Plugin::getView();
        $view->assign('posts', $posts);

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