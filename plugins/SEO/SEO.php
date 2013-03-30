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
    const GOOGLE_PAGE_RANK_METRIC_NAME = 'ggl_page_rank';
    const GOOGLE_INDEXED_PAGE_COUNT = 'ggl_indexed_pages';
    const ALEXA_RANK_METRIC_NAME = 'alexa_rank';
    const DMOZ_METRIC_NAME = 'dmoz';
    const BING_INDEXED_PAGE_COUNT = 'bing_indexed_pages';
    const BACKLINK_COUNT = 'backlinks';
    const REFERRER_DOMAINS_COUNT = 'referrer_domains';
    const SITE_AGE_LABEL = 'site_age';
    const DONE_ARCHIVE_NAME = 'SEO_done'; // TODO docs
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
        $reports[] = array( // TODO: should be hidden in report metadata list...
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
        Piwik_AddWidget('SEO', 'SEO_SeoRankings', 'SEO', 'getRank');
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
     * TODO
     */
    public function archiveDay( $notification )
    {
        $archiveProcessing = $notification->getNotificationObject();
        
        // if the archive processing date is not today, do nothing. we cannot
        // get data from the past, unfortunately. TODO: doublecheck timezone logic
        $today = Piwik_Date::factory('now', $archiveProcessing->site->getTimezone())->toString();
        if ($archiveProcessing->period->getDateStart()->toString() != $today) {
            return;
        }
        
        // check if seo archiving has been completed successfully
        $archive = $archiveProcessing->makeArchiveQuery();
        $archive->disableArchiving(); // TODO: should be done by archive processing::makeArchiveQuery. also should work w/ Archive_Array (see below as well)
        $isSEOArchivingDone = $archive->getNumeric(self::DONE_ARCHIVE_NAME);
        
        // if archiving has not been completed successfully, issue HTTP requests
        // otherwise, use old statistics
        if ($isSEOArchivingDone) {
            $dataTable = $archive->getDataTableFromNumeric(self::$seoMetrics, $formatResult = false);
            
            $stats = array();// TODO code redundancy w/ below
            if ($dataTable->getRowsCount() != 0) {
                $stats = $dataTable->getFirstRow()->getColumns();
            }
        } else {
            // TODO: move this to a private function
            $siteUrl = $archive->getSite()->getMainUrl();// TODO (have to check alias URLs and other domains?)
        
            $rank = new Piwik_SEO_RankChecker($siteUrl);
            $statNamesToGetters = array(
                self::GOOGLE_PAGE_RANK_METRIC_NAME => 'getPageRank',
                self::GOOGLE_INDEXED_PAGE_COUNT => 'getIndexedPagesGoogle',
                self::ALEXA_RANK_METRIC_NAME => 'getAlexaRank',
                self::DMOZ_METRIC_NAME => 'getDmoz',
                self::BING_INDEXED_PAGE_COUNT => 'getIndexedPagesBing',
                self::BACKLINK_COUNT => 'getExternalBacklinkCount',
                self::REFERRER_DOMAINS_COUNT => 'getReferrerDomainCount',
            );
            // TODO: how to not use too many majestic API requests? need to use bulk request format.
        
            $stats = array();
            foreach ($statNamesToGetters as $statName => $rankCheckerMethodName) {
                $stats[$statName] = call_user_func(array($rank, $rankCheckerMethodName));
            }
        }
        
        // insert statistics w/ new idarchive
        foreach ($stats as $name => $value) {
            $archiveProcessing->insertNumericRecord($name, $value);
        }
        
        // finished SEO archiving
        $archiveProcessing->insertNumericRecord(self::DONE_ARCHIVE_NAME, 1);
    }
    
    /*
     * TODO: doing one site at a time is a big no-no, WAY TOO INEFFICIENT. ALSO, must limit to 100 sites or so (see skype for name of option)
     */
    public function archivePeriod( $notification )
    {
        $archiveProcessing = $notification->getNotificationObject();
        
        // get seo metrics for the last day in the current period
        $lastDay = $archiveProcessing->period->getDateEnd()->toString();
        $archive = $archiveProcessing->makeArchiveQuery($idSite = false, $period = 'day', $date = $lastDay);
        $archive->disableArchiving();
        
        $dataTable = $archive->getDataTableFromNumeric(self::$seoMetrics);
        
        $stats = array();
        if ($dataTable->getRowsCount() != 0) {
            $stats = $dataTable->getFirstRow()->getColumns();
        }
        
        // insert metrics for new period
        foreach ($stats as $name => $value) {
            $archiveProcessing->insertNumericRecord($name, $value);
        }
        
        // TODO insert record for done?
    }
    
    /**
     * TODO
     */
    public static function getSiteBirthOptionName( $idSite )
    {
        return self::SITE_BIRTH_OPTION_PREFIX.$idSite;
    }
}
