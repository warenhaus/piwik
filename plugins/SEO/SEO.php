<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_SEO
 */

/**
 * @see plugins/SEO/ArchiveProcessing.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/SEO/ArchiveProcessing.php';

/**
 * @package Piwik_SEO
 */
class Piwik_SEO extends Piwik_Plugin
{
    /** Archive names for SEO metrics. */
    // TODO: use SEO_ prefix for metric names? no need for getPluginNameForMetric event handler, then.
    const GOOGLE_PAGE_RANK_METRIC_NAME = 'google_page_rank';
    const GOOGLE_INDEXED_PAGE_COUNT = 'google_indexed_pages';
    const ALEXA_RANK_METRIC_NAME = 'alexa_rank';
    const DMOZ_METRIC_NAME = 'dmoz';
    const BING_INDEXED_PAGE_COUNT = 'bing_indexed_pages';
    const BACKLINK_COUNT = 'backlinks';
    const REFERRER_DOMAINS_COUNT = 'referrer_domains';
    
    /**
     * TODO
     */
    const SEO_STATS_ARCHIVE_NAME = 'SEO';
    
    /**
     * Site creation time is stored as an option, and age is calculated in the API methods.
     * This is the label used in resulting datatables.
     */
    const SITE_AGE_LABEL = 'site_age';
    
    const SITE_CREATION_OPTION_PREFIX = 'site_creation_';
    
    public static $seoMetrics = array(
        self::GOOGLE_PAGE_RANK_METRIC_NAME,
        self::GOOGLE_INDEXED_PAGE_COUNT,
        self::ALEXA_RANK_METRIC_NAME,
        self::DMOZ_METRIC_NAME,
        self::BING_INDEXED_PAGE_COUNT,
        self::BACKLINK_COUNT,
        self::REFERRER_DOMAINS_COUNT
    );
    
    public static $seoMetricTranslations = array(
        self::GOOGLE_PAGE_RANK_METRIC_NAME => 'Google PageRank',
        self::GOOGLE_INDEXED_PAGE_COUNT => 'SEO_Google_IndexedPages',
        self::ALEXA_RANK_METRIC_NAME => 'SEO_AlexaRank',
        self::DMOZ_METRIC_NAME => 'SEO_Dmoz',
        self::BING_INDEXED_PAGE_COUNT => 'SEO_Bing_IndexedPages',
        self::BACKLINK_COUNT => 'SEO_ExternalBacklinks',
        self::REFERRER_DOMAINS_COUNT => 'SEO_ReferrerDomains',
        self::SITE_AGE_LABEL => 'SEO_DomainAge'
    );
    
    public function getInformation()
    {
        return array(
            'description'     => 'This Plugin extracts and displays SEO metrics: Alexa web ranking, Google Pagerank, number of Indexed pages and backlinks of the currently selected website.',
            'author'          => 'Piwik',
            'author_homepage' => 'http://piwik.org/',
            'version'         => Piwik_Version::VERSION,
        );
    }

    public function getListHooksRegistered()
    {
        $hooks = array(
            'WidgetsList.add'                          => 'addWidgets',
            'Archive.getPluginNameForMetric'           => 'getPluginNameForMetric',
            'API.getReportMetadata'                    => 'getReportMetadata',
            'TaskScheduler.getScheduledTasks'          => 'getScheduledTasks',
            'ArchiveProcessing.getArchiveBaseName'     => 'getArchiveBaseName',
        );
        return $hooks;
    }
    
    public function getScheduledTasks($notification)
    {
        $tasks = &$notification->getNotificationObject();
        
        $archiveSEOMetricsTask = new Piwik_ScheduledTask(
            $this, 'archiveSEOMetrics', null, new Piwik_ScheduledTime_Daily(), Piwik_ScheduledTask::HIGH_PRIORITY
        );
        $tasks[] = $archiveSEOMetricsTask;
    }
    
    public function getArchiveBaseName($notification)
    {
        $archiveBaseName = &$notification->getNotificationObject();
        $pluginName = $notification->getNotificationInfo();
        
        if ($pluginName == 'SEO') {
            $archiveBaseName = self::SEO_STATS_ARCHIVE_NAME;
        }
    }

    public function getReportMetadata($notification)
    {
        $reports = &$notification->getNotificationObject();
        
        $metrics = array();
        foreach (self::$seoMetrics as $name) {
            $metrics[$name] = Piwik_Translate(self::$seoMetricTranslations[$name]);
        }
        
        $reports[] = array(
            'category' => 'SEO',
            'name' => Piwik_Translate('SEO_SeoRankings'),
            'module' => 'SEO',
            'action' => 'getSEOStats',
            'dimension' => Piwik_Translate('General_Value'),
            'metrics' => $metrics,
            'metricsDocumentation' => array(),
            'processedMetrics' => false,
            'order' => 1
        );
    }
    
    public function addWidgets()
    {
        Piwik_AddWidget('SEO', 'SEO_SeoRankings', 'SEO', 'getSEOStatsForSite');
    }
    
    public function getPluginNameForMetric( $notification )
    {
        $pluginName =& $notification->getNotificationObject();
        $metricName = $notification->getNotificationInfo();
        
        if ($pluginName === false
            && in_array($metricName, self::$seoMetrics)
        ) {
            $pluginName = 'SEO';
        }
    }
    
    /**
     * Archives SEO metrics for today. Also only archives metrics for the first N sites
     * (determined by the seo_max_sites_to_archive_metrics_for config option).
     * 
     * If stats for today have already been requested, we do not do it again.
     * Instead, we request the last stats and re-insert them w/ a new idarchive.
     * 
     * @param Piwik_Event_Notification $notification
     */
    public function archiveSEOMetrics($idSite = false, $date = false)// TODO: fix parameter styles
    {
        // when testing, make sure we can archive for any date
        if (Piwik_SEO_ArchiveProcessing::$customRankCheckerClassName === null
            || $date === false
        ) {
            $date = Piwik_Date::factory('today');
        }
        
        if ($idSite === false) {
            $idSitesToArchiveFor = $this->getSitesToArchiveSEOMetricsFor();
        } else {
            $idSitesToArchiveFor = array($idSite);
        }
        
        $result = array();
        foreach ($idSitesToArchiveFor as $idSite) { // TODO: do more than one at once
            $archiveProcessing = new Piwik_SEO_ArchiveProcessing($idSite, $date);
            $archiveProcessing->enableArchiving();
            
            $idArchive = $archiveProcessing->loadArchive();
            if (empty($idArchive)) { // TODO: perhaps launchArchiving could take care of the loadArchive() call?
                $stats = $archiveProcessing->launchArchiving();
                if (!empty($stats)) {
                    $result[$idSite] = $stats;
                }
            }
        }
        return $result;
    }
    
    /**
     * Returns the name of the option that holds the birth time for a site.
     * 
     * @return string
     */
    public static function getSiteCreationOptionName( $idSite )
    {
        return self::SITE_CREATION_OPTION_PREFIX.$idSite;
    }
    
    /**
     * Gets the list of site IDs for whom SEO metrics should be queried and archived.
     * We don't do it for all sites since making that many HTTP requests can take too
     * much time.
     */
    private function shouldArchiveForSite($idSite)
    {
        // if the browser initiated archiving, then we always archive
        if (!Piwik_Common::isPhpCliMode()) {
            return true;
        }
        
        $idSitesToArchiveFor = $this->getSitesToArchiveSEOMetricsFor();
        return in_array($idSite, $idSitesToArchiveFor);
    }
    
    /**
     * TODO
     */
    private function getSitesToArchiveSEOMetricsFor()
    {
        $seoMaxSitesToArchiveMetricsFor =
            Piwik_Config::getInstance()->General['seo_max_sites_to_archive_metrics_for'];
        
        $allIdSites = Piwik_SitesManager_API::getInstance()->getAllSitesId();
        return array_slice($allIdSites, 0, $seoMaxSitesToArchiveMetricsFor);
    }
    //TODO: go through all new functions and check if they are still used.    
    
    /**
     * Translates SEO metric name using a set of translations. Used as datatable
     * filter callback.
     * 
     * @param string $metricName
     * @param array $translations
     */
    public static function translateSeoMetricName( $metricName, $translations )
    {
        if (isset($translations[$metricName])) {
            return Piwik_Translate($translations[$metricName]);
        }
        return $metricName;
    }
}
