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
class Piwik_SEO_Controller extends Piwik_Controller
{
    public function getSEOStatsForSite()
    {
        $idSite = Piwik_Common::getRequestVar('idSite');
        $site = new Piwik_Site($idSite);

        $url = $site->getMainUrl();
        
        $today = Piwik_Date::factory('now', $site->getTimezone())->toString();
        
        $date = Piwik_Common::getRequestVar('date', $today, 'string');
        $period = Piwik_Common::getRequestVar('period', 'day', 'string');
        if ($period == 'day') {
            $date = $today;
        }
        
        $dataTable = Piwik_SEO_API::getInstance()->getSEOStats($idSite, 'day', $date, $full = true);
        
        $view = $this->getSEOStatsWidgetView($dataTable, $url, $date);
        echo $view->render();
    }
    
    public function getSEOStatsForUrl()
    {
        $url = urldecode(Piwik_Common::getRequestVar('url', $default = null, 'string'));
        
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            $url = 'http://' . $url;
        }
        
        $dataTable = Piwik_SEO_API::getInstance()->getSEOStatsForUrl($url);
        
        $view = $this->getSEOStatsWidgetView($dataTable, $url, $date = null);
        echo $view->render();
    }
    
    private function getSEOStatsWidgetView($dataTable, $url, $date)
    {
        $view = Piwik_View::factory('index');
        $view->urlToRank = Piwik_SEO_RankChecker::extractDomainFromUrl($url);
        
        if ($date !== null) {
            $view->prettyDate = Piwik_Date::factory($date)->getLocalized('%shortMonth% %day%');
        }
        
        $renderer = Piwik_DataTable_Renderer::factory('php');
        $renderer->setSerialize(false);
        $view->ranks = $renderer->render($dataTable);
        
        return $view;
    }
}
