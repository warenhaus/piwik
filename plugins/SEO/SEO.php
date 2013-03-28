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
	
	const DONE_ARCHIVE_NAME = 'SEO_done'; // TODO docs
	
	public static $seoMetrics = array(
		self::GOOGLE_PAGE_RANK_METRIC_NAME,
		self::GOOGLE_INDEXED_PAGE_COUNT,
		self::ALEXA_RANK_METRIC_NAME,
		self::DMOZ_METRIC_NAME,
		self::BING_INDEXED_PAGE_COUNT,
		self::BACKLINK_COUNT,
		self::REFERRER_DOMAINS_COUNT
	);
	
	const SITE_BIRTH_OPTION_PREFIX = 'site_birth_';
	
	public function getInformation()
	{
		return array(
			'description' => 'This Plugin extracts and displays SEO metrics: Alexa web ranking, Google Pagerank, number of Indexed pages and backlinks of the currently selected website.',
			'author' => 'Piwik',
			'author_homepage' => 'http://piwik.org/',
			'version' => Piwik_Version::VERSION,
		);
	}
	
	function getListHooksRegistered()
	{
		$hooks = array(
			'WidgetsList.add' => 'addWidgets',
			'TaskScheduler.getScheduledTasks' => 'getScheduledTasks',
			'Archive.getPluginOfMetric' => 'getPluginOfMetric',
			'API.getReportMetadata' => 'getReportMetadata',
		);
		return $hooks;
	}	
	
	public function getReportMetadata($notification)
	{
		$reports = &$notification->getNotificationObject();
		$reports[] = array(
			'category' => 'SEO',
			'name' => Piwik_Translate('SEO_SeoRankings'),
			'module' => 'SEO',
			'action' => 'getSEOStats',
			'dimension' => Piwik_Translate('General_Value'),
			'order' => 1
		);
	}
	
	function addWidgets()
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
				|| $metricName == self::DONE_ARCHIVE_NAME))
		{
			$pluginName = 'SEO';
		}
	}
	
	public function getScheduledTasks( $notification )
	{
		$tasks = &$notification->getNotificationObject();
		
		// TODO: explain priority
		$archiveSEOStats = new Piwik_ScheduledTask(
			$this, 'archiveSEOStats', null, new Piwik_ScheduledTime_Daily(), Piwik_ScheduledTask::HIGH_PRIORITY
		);
		
		$tasks[] = $archiveSEOStats;
	}
	
	public function archiveSEOStats()
	{
		$allIdSites = Piwik_SitesManager_API::getInstance()->getAllSitesId();
		foreach ($allIdSites as $idSite)
		{
			self::archiveSEOStatsFor($idSite);
		}
	}
	
	/**
	 * TODO
	 * TODO: doing one site at a time is a big no-no, WAY TOO INEFFICIENT
	 */
	public static function archiveSEOStatsFor( $idSite )
	{
		$archive = Piwik_Archive::build($idSite, 'day', 'today');
		$archive->setRequestedReport('SEO_Metrics');
		$archive->prepareArchive();
		$archive->setIdArchive(Piwik_ArchiveProcessing::TIME_OF_DAY_INDEPENDENT);
		
		$today = $archive->getPeriod()->getDateStart();
		$doneMetricName = self::getMetricArchiveName(self::DONE_ARCHIVE_NAME, $idSite, $today);
		
		$isArchivingDone = $archive->getNumeric($doneMetricName, $checkIfVisits = false);
		if ($isArchivingDone != 0)
		{
			return false; // do not perform any HTTP requests if the archiving process is finished
		}
		
		$archiveProcessing = $archive->archiveProcessing;

		$siteUrl = Piwik_Site::getMainUrlFor($idSite);// TODO (have to check alias URLs and other domains?)
		
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
		foreach ($statNamesToGetters as $statName => $rankCheckerMethodName)
		{
			$stats[$statName] = call_user_func(array($rank, $rankCheckerMethodName));
		}
		
		foreach ($stats as $archiveName => $archiveValue)
		{
			$archiveName = self::getMetricArchiveName($archiveName, $idSite, $today);
			$archiveProcessing->insertNumericRecord($archiveName, $archiveValue);
		}
		$archiveProcessing->insertNumericRecord($doneMetricName, 1);
		
		Piwik_SEO_API::getInstance()->getSiteBirthTime($idSite); // will cache if not already cached
		
		return $stats;
	}
	
	/**
	 * TODO
	 */
	public static function getSiteBirthOptionName( $idSite )
	{
		return self::SITE_BIRTH_OPTION_PREFIX.$idSite;
	}
	
	/**
	 * TODO
	 */
	public static function getMetricArchiveName( $name, $idSite, $day )
	{
		return $name.'-'.$idSite.'-'.$day;
	}
}
