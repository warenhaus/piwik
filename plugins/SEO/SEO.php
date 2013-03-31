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
     * Site birth time is stored as an option, and age is calculated in the API methods.
     * This is the label used in resulting datatables.
     */
    const SITE_AGE_LABEL = 'site_age';
    
    const SITE_BIRTH_OPTION_PREFIX = 'site_birth_';
    
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
            'WidgetsList.add' => 'addWidgets',
            'Archive.getPluginOfMetric' => 'getPluginOfMetric',
            'API.getReportMetadata' => 'getReportMetadata',
            'ArchiveProcessing_Day.compute' => 'archiveDay',
            'ArchiveProcessing_Period.compute' => 'archivePeriod',
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
            'metrics' => array_merge($metrics, array('site_age' => self::$seoMetricTranslations['site_age'])),
            'processedMetrics' => false,
            'order' => 1
        );
        $reports[] = array(
            'category' => 'SEO',
            'name' => Piwik_Translate('SEO_SeoRankings').' without metadata',
            'module' => 'SEO',
            'action' => 'getSEOStatsWithoutMetadata',
            'dimension' => Piwik_Translate('General_Value'),
            'metrics' => $metrics,
            'processedMetrics' => false,
            'order' => 2
        );
    }
    
    public function addWidgets()
    {
        Piwik_AddWidget('SEO', 'SEO_SeoRankings', 'SEO', 'getSEOStatsForSite');
    }
    
    public function getPluginOfMetric( $notification )
    {
        $pluginName =& $notification->getNotificationObject();
        $metricName = $notification->getNotificationInfo();
        
        $parts = explode('-', $metricName);
        $metricName = reset($parts);
        
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
        
        if (!$this->shouldArchiveForSite($archiveProcessing->site->getId())) {
            return;
        }
        
        // if the archive processing date is not today, do nothing. we cannot
        // get data from the past, unfortunately. TODO: doublecheck timezone logic
        $today = Piwik_Date::factory('now', $archiveProcessing->site->getTimezone())->toString();
        if ($archiveProcessing->period->getDateStart()->toString() != $today) {
            return;
        }
        
        // check if seo archiving has been completed successfully
        $archive = $archiveProcessing->makeArchiveQuery();
        $archive->performQueryWhenNoVisits();
        $isSEOArchivingDone = $archive->getNumeric(self::DONE_ARCHIVE_NAME);
        
        // if archiving has not been completed successfully, issue HTTP requests
        // otherwise, use old statistics
        if ($isSEOArchivingDone) {
            $dataTable = $archive->getDataTableFromNumeric(self::$seoMetrics);
            $stats = $this->getColumnsOfFirstRow($dataTable);
        } else {
            $siteUrl = $archive->getSite()->getMainUrl();
        
            $rank = new Piwik_SEO_RankChecker($siteUrl);
            $stats = $rank->getAllStats();
        }
        
        // insert statistics w/ new idarchive
        foreach ($stats as $name => $value) {
            $archiveProcessing->insertNumericRecord($name, $value);
        }
        
        // finished SEO archiving (unless the Majestic API returned nothing)
        if ($stats[self::BACKLINK_COUNT] !== false
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
        
        // get seo metrics for the last day in the current period
        $lastDay = $archiveProcessing->period->getDateEnd()->toString();
        $archive = $archiveProcessing->makeArchiveQuery($idSite = false, $period = 'day', $date = $lastDay);
        $archive->performQueryWhenNoVisits();
        
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
    public static function getSiteBirthOptionName( $idSite )
    {
        return self::SITE_BIRTH_OPTION_PREFIX.$idSite;
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
        $seoMaxSitesToArchiveMetricsFor =
            Piwik_Config::getInstance()->General['seo_max_sites_to_archive_metrics_for'];
        
        $allIdSites = Piwik_SitesManager_API::getAllSitesId();
        $idSitesToArchiveFor = array_slice($allIdSites, 0, 100);
        
        return in_array($idSite, $idSitesToArchiveFor);
    }
}
