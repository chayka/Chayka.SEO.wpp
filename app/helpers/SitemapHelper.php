<?php

namespace Chayka\SEO;

use Chayka\WP\Helpers\DbHelper;
use Chayka\WP\Models\PostModel;

class SitemapHelper{

    public static function buildIndex(){

    }

    public static function renderIndex($isCache = false, $forceRefresh = false){
        $table = PostModel::getDbTable();
        $indexPath = ABSPATH.'sitemap.xml';
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

    public static function renderPostTypeIndex($postType, $year4d = 0, $month = 0){
        $posts = [];
        $table = PostModel::getDbTable();
        $slq = DbHelper::prepare(
            "SELECT ID, post_date, post_modified, post_title, post_name, post_excerpt, post_type
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