<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * B
 * @category Piwik_Plugins
 * @package Piwik_SEO
 */
 
/**
 * @package Piwik_SEO
 */
class Piwik_SEO_Controller extends Piwik_Controller
{	
	function getRank()
	{
		$idSite = Piwik_Common::getRequestVar('idSite'); 
		$site = new Piwik_Site($idSite);

		$url = urldecode(Piwik_Common::getRequestVar('url', '', 'string'));
		
		if(!empty($url) && strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
			$url = 'http://'.$url;
		}
		
		$url = $site->getMainUrl(); // TODO: remove $url and url text entry in UI
		
		$today = Piwik_Date::today()->toString();
		
		$date = Piwik_Common::getRequestVar('date', $today, 'string');
		$period = Piwik_Common::getRequestVar('period', 'day', 'string');
		if ($period == 'day')
		{
			$date = Piwik_Date::factory('today')->toString();
		}
		
		$dataTable = Piwik_SEO_API::getInstance()->getSEOStats($idSite, 'day', $date);
		
		$view = Piwik_View::factory('index');
		$view->urlToRank = Piwik_SEO_RankChecker::extractDomainFromUrl($url);
		
		$renderer = Piwik_DataTable_Renderer::factory('php');
		$renderer->setSerialize(false);
		$view->ranks = $renderer->render($dataTable);
		$view->prettyDate = Piwik_Date::factory($date)->getLocalized('%shortMonth% %day%');
		echo $view->render();
	}
}
