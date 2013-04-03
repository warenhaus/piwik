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
 * @package Piwik_SEO
 */
class Piwik_SEO extends Piwik_Plugin
{
    /** Archive names for SEO metrics. */
    const GOOGLE_PAGE_RANK_METRIC_NAME = 'ggl_page_rank';
    const GOOGLE_INDEXED_PAGE_COUNT = 'ggl_indexed_pages';
    const ALEXA_RANK_METRIC_NAME = 'alexa_rank';
    const DMOZ_METRIC_NAME = 'dmoz';
    const BING_INDEXED_PAGE_COUNT = 'bing_indexed_pages';
    const BACKLINK_COUNT = 'backlinks';
    const REFERRER_DOMAINS_COUNT = 'referrer_domains';
    
    /**
     * If SEO stats are successfully obtained (ie, no third party APIs fail or block our IP),
     * an archive w/ this name and the value 1 is stored. If found when archiving for a
     * period/site, no new HTTP requests are made.
     */
    const DONE_ARCHIVE_NAME = 'SEO_done';
    
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
    
    /** Name of a custom RankChecker class to use. Used for testing this plugin. */
    public static $customRankCheckerClassName = null;
    
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
            'ArchiveProcessing_Day.computeNoVisits'    => 'archiveDay',
            'ArchiveProcessing_Period.computeNoVisits' => 'archivePeriod',
        );
        return $hooks;
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
            && (in_array($metricName, self::$seoMetrics)
                || $metricName == self::DONE_ARCHIVE_NAME)
        ) {
            $pluginName = 'SEO';
        }
    }
    
    /**
     * Archives SEO metrics for today. If the date requested for archiving is not
     * today, nothing is done. Also only archives metrics for the first N sites
     * (determined by the seo_max_sites_to_archive_metrics_for config option).
     * 
     * If stats for today have already been requested, we do not do it again.
     * Instead, we request the last stats and re-insert them w/ a new idarchive.
     * 
     * TODO: in order to not use too many majestic API requests, we can issue
     *       up to 100 sites at once in a request, but the archiving process is
     *       not built for archiving multiple sites at once.
     * 
     * @param Piwik_Event_Notification $notification
     */
    public function archiveDay( $notification )
    {
        $archiveProcessing = $notification->getNotificationObject();
        
        $site = $archiveProcessing->site;
        $date = $archiveProcessing->period->getDateStart();
        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())
            || !$this->shouldArchiveForSite($site->getId())
            || !$this->shouldArchiveForDate($date, $site->getTimezone())
        ) {
            return;
        }
        
        $archive = $this->makeArchiveQuery($archiveProcessing);
        if ($archive === null) {
            return;
        }
        
        // check if seo archiving has been completed successfully
        $isSEOArchivingDone = $archive->getNumeric(self::DONE_ARCHIVE_NAME);
        
        // if archiving has not been completed successfully, issue HTTP requests
        // otherwise, use archived statistics
        if ($isSEOArchivingDone) {
            $dataTable = $archive->getDataTableFromNumeric(self::$seoMetrics);
            $stats = $this->getColumnsOfFirstRow($dataTable);
        } else {
            $siteUrl = $archive->getSite()->getMainUrl();
        
            $rankChecker = self::makeRankChecker($siteUrl, $date);
            $stats = $rankChecker->getAllStats();
        }
        
        // insert statistics w/ new idarchive
        foreach ($stats as $name => $value) {
            $archiveProcessing->insertNumericRecord($name, $value);
        }
        
        // finished SEO archiving (unless the Majestic API returned nothing)
        if (isset($stats[self::BACKLINK_COUNT])
            && $stats[self::BACKLINK_COUNT] !== false
            && isset($stats[self::REFERRER_DOMAINS_COUNT])
            && $stats[self::REFERRER_DOMAINS_COUNT] !== false
        ) {
            $archiveProcessing->insertNumericRecord(self::DONE_ARCHIVE_NAME, 1);
        }
    }
    
    /**
     * Archives SEO metrics for a non-day period. This function gets the metrics
     * for the last day in the period and stores them as the SEO metrics for the
     * period.
     * 
     * @param Piwik_Event_Notification $notification
     */
    public function archivePeriod( $notification )
    {
        $archiveProcessing = $notification->getNotificationObject();
        
        $site = $archiveProcessing->site;
        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())
            || !$this->shouldArchiveForSite($site->getId())
        ) {
            return;
        }
        
        // get seo metrics for the last day in the current period
        $lastDay = $archiveProcessing->period->getDateEnd()->toString();
        $archive = $this->makeArchiveQuery($archiveProcessing, $period = 'day', $date = $lastDay);
        if ($archive === null) {
            return;
        }
        
        $dataTable = $archive->getDataTableFromNumeric(self::$seoMetrics);
        $stats = $this->getColumnsOfFirstRow($dataTable);
        
        // insert metrics for new period
        foreach ($stats as $name => $value) {
            $archiveProcessing->insertNumericRecord($name, $value);
        }
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
    
    private function getColumnsOfFirstRow($dataTable)
    {
        $result = array();
        if ($dataTable->getRowsCount() != 0) {
            $result = $dataTable->getFirstRow()->getColumns();
        }
        return $result;
    }
    
    /**
     * Gets the list of site IDs for whom SEO metrics should be queried and archived.
     * We don't do it for all sites since making that many HTTP requests can take too
     * much time.
     */
    private function shouldArchiveForSite($idSite)
    {
        // if the browser initiated archiving, then we always archive
        if (!Piwik_TaskScheduler::isTaskBeingExecuted()) {
            return true;
        }
        
        $seoMaxSitesToArchiveMetricsFor =
            Piwik_Config::getInstance()->General['seo_max_sites_to_archive_metrics_for'];
        
        $allIdSites = Piwik_SitesManager_API::getAllSitesId();
        $idSitesToArchiveFor = array_slice($allIdSites, 0, $seoMaxSitesToArchiveMetricsFor);
        
        return in_array($idSite, $idSitesToArchiveFor);
    }
    
    /**
     * Returns true if we should archive SEO metrics for a date. Archiving
     * is not done if the archive processing date is not today, since we
     * cannot get data from the past.
     * 
     * @param Piwik_Date $date
     * @param string $siteTimezone Timezone of the site we're archiving data for.
     * @return bool
     */
    private function shouldArchiveForDate($date, $siteTimezone)
    {
        $today = Piwik_Date::factory('now', $siteTimezone)->toString();
        return $date->toString() == $today || self::$customRankCheckerClassName !== null;
    }
    
    /**
     * Creates a RankChecker instance that can be used to query third party services
     * for SEO stats. If self::$customRankCheckerClassName is set to a non-null value,
     * that result is an instance of that class name.
     * 
     * All code that uses the Piwik_SEO_RankChecker class should use this function so
     * the SEO plugin can be tested.
     * 
     * @param string $url
     * @param Piwik_Date|null The archiving date. For testing purposes.
     */
    public static function makeRankChecker($url, $date = null)
    {
        if (self::$customRankCheckerClassName === null) {
            return new Piwik_SEO_RankChecker($url);
        } else {
            return new self::$customRankCheckerClassName($url, $date);
        }
    }
    
    /**
     * Returns a Piwik_Archive instance that can be used to query existing SEO metrics
     * while archiving for another date.
     */
    private function makeArchiveQuery($archiveProcessing, $period = false, $date = false)
    {
        $archive = $archiveProcessing->makeArchiveQuery($idSite = false, $period, $date);
        $archive->setRequestedReport('SEO_Metrics');
        
        // in some cases (like when the date is after today), archiveProcessing will be null
        // in this case, we should avoid SEO archiving
        $archive->prepareArchive();
        if ($archive->archiveProcessing === null) {
            return null;
        }
        
        $archive->performQueryWhenNoVisits();
        
        return $archive;
    }
    
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
