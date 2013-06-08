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
 * @see plugins/Referers/functions.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/Referers/functions.php';

/**
 * The SEO API lets you access a list of SEO metrics for the specified URL: Google Pagerank, Goolge/Bing indexed pages
 * Alexa Rank, age of the Domain name and count of DMOZ entries.
 *
 * @package Piwik_SEO
 */
class Piwik_SEO_API
{
    static private $instance = null;

    /**
     * @return Piwik_SEO_API
     */
    static public function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    
    /**
     * Returns SEO stats and site age with metadata (including the logo and any
     * pertinent links).
     * 
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param bool $full Whether to return extra metadata and site age value or not.
     * @return Piwik_DataTable
     */
    public function getSEOStats( $idSite, $period, $date, $full = false )
    {
        $result = $this->getSEOStatsWithoutMetadata($idSite, $period, $date);
        if ($full) {
            $result->filter('ColumnDelete', array('label'));
            
            // add row for site birth (only if $result is a table)
            if ($result instanceof Piwik_DataTable) {
                $siteCreation = $this->getSiteCreationTime($idSite);
                
                $row = $result->getFirstRow();
                $row->setColumn('site_creation', $siteCreation);
            }
            
            $result = $this->splitColumnsIntoRows($result);
            $this->addSEOMetadataToStatsTable($result, Piwik_Site::getMainUrlFor($idSite));
            $this->translateSEOMetricLabels($result);
        }
        return $result;
    }
    
    /**
     * Returns the birth time of a website's domain in seconds since the epoch.
     * 
     * @param int $idSite
     * @return int
     * @ignore
     */
    public function getSiteCreationTime($idSite)
    {
        $siteCreationOption = Piwik_SEO::getSiteCreationOptionName($idSite);
        $siteCreationTime = Piwik_GetOption($siteCreationOption);
        
        if ($siteCreationTime === false) {
            $rank = Piwik_SEO_ArchiveProcessing::makeRankChecker(Piwik_Site::getMainUrlFor($idSite));
            
            $siteAge = $rank->getAge($prettyFormatAge = false);
            $siteCreationTime = time() - $siteAge;
            
            Piwik_SetOption($siteCreationOption, $siteCreationTime);
        }
        
        return $siteCreationTime;
    }
    
    /**
     * Returns SEO statistics for a URL.
     *
     * @param string $url URL to request SEO stats for
     * @return Piwik_DataTable
     */
    public function getSEOStatsForUrl($url)
    {
        Piwik::checkUserHasSomeViewAccess();
        
        $rankChecker = Piwik_SEO_ArchiveProcessing::makeRankChecker($url);
        $stats = $rankChecker->getAllStats();
        $stats[Piwik_SEO::SITE_AGE_LABEL] = $rankChecker->getAge($prettyFormatAge = true);
        
        $dataTable = new Piwik_DataTable();
        $dataTable->addRowsFromArrayWithIndexLabel($stats);
        $this->addSEOMetadataToStatsTable($dataTable, $url);
        $this->translateSEOMetricLabels($dataTable);
        return $dataTable;
    }
    
    /**
     * @deprecated
     */
    public function getRank($url)
    {
        return $this->getSEOStatsForUrl($url);
    }
    
    /**
     * Returns SEO Stats in one row without any metadata. Does not return the
     * age of a website.
     */
    private function getSEOStatsWithoutMetadata($idSite, $period, $date)
    {
        $forceIndexedBySite = $forceIndexedByDate = false;
        
        if ($idSite == 'all') {
            $forceIndexedBySite = true;
        }
        $siteIds = Piwik_Site::getIdSitesFromIdSitesString($idSite);
        
        if (Piwik_Archive::isMultiplePeriod($date, $period)) {
            $forceIndexedByDate = true;
            
            $oPeriod = new Piwik_Period_Range($period, $date);
            $periods = $oPeriod->getSubperiods();
        } else {
            if (count($siteIds) == 1) {
                $oSite = new Piwik_Site($siteIds[0]);
            } else {
                $oSite = null;
            }
            
            $oPeriod = Piwik_Archive::makePeriodFromQueryParams($oSite, $period, $date);
            $periods = array($oPeriod);
        }
        
        // TODO: code redundancy w/ Archive.php (everything above this line)
        if ($period != 'day') {
            $originalPeriodsByEndDate = array();
            
            foreach ($periods as &$oPeriod) {
                $endDate = $oPeriod->getDateEnd();
                $originalPeriodsByEndDate[$endDate->toString()] = $oPeriod;
                
                $oPeriod = Piwik_Period::factory('day', $endDate);
            }
        }
        
        $segment = new Piwik_Segment('', $siteIds);
        $archive = new Piwik_Archive($siteIds, $periods, $segment, $forceIndexedBySite, $forceIndexedByDate);
        
        // if no SEO metrics have been archived and querying for one site and today, get the SEO metrics
        // via HTTP.
        if (!$archive->isQueryingForMultipleSites()
            && !$archive->isQueryingForMultiplePeriods()
            && reset($periods)->getDateEnd()->isToday()
            && !$archive->doArchivesExistFor(Piwik_SEO::$seoMetrics)
        ) { 
            $data = Piwik_PluginsManager::getInstance()->getLoadedPlugin('SEO')->archiveSEOMetrics(reset($siteIds));
            
            $result = new Piwik_DataTable_Simple();
            $result->addRow(new Piwik_DataTable_Row(array(Piwik_DataTable_Row::COLUMNS => reset($data))));
        }
        
        if (!isset($result)) {
            $result = $archive->getDataTableFromNumeric(Piwik_SEO::$seoMetrics);
        }
        
        // reset datatable period metadata to be the non-day periods if data for non-day periods
        // were requested
        if ($period != 'day') {
            // TODO: no way to do this w/o a hacky check for Piwik_DataTable_Array::getKeyName() == 'date'.
            //        can be removed if Piwik_DataTable_Array is removed.
        }
        
        $result->filter('ColumnCallbackAddColumn', array(array(), 'label', 'Piwik_Translate', array('SEO_Stats_js')));
        return $result;
    }

    private function splitColumnsIntoRows($table)
    {
        if ($table instanceof Piwik_DataTable_Array) {
            $result = $table->getEmptyClone();
            foreach ($table->getArray() as $label => $childTable) {
                $transformedChild = $this->splitColumnsIntoRows($childTable);
                $result->addTable($transformedChild, $label);
            }
            return $result;
        } else {
            $result = $table->getEmptyClone();
            
            $firstRow = $table->getFirstRow();
            if ($firstRow) {
                foreach ($firstRow->getColumns() as $metricName => $value) {
                    if ($value === false) {
                        $value = 0;
                    }
                
                    $columns = array('label' => $metricName, 'value' => $value);
                    $row = new Piwik_DataTable_Row(array(Piwik_DataTable_Row::COLUMNS => $columns));
                     
                    $result->addRow($row);
                }
            }
            return $result;
        }
    }
    
    private function addSEOMetadataToStatsTable($table, $url)
    {
        $metadataToAdd = $this->getSEOMetadata($url);
        $this->addSEOMetadataToRows($table, $metadataToAdd);
    }
    
    private function getSEOMetadata($url)
    {
        // set metadata for individual rows
        $googleLogo = Piwik_getSearchEngineLogoFromUrl('http://google.com');
        $bingLogo = Piwik_getSearchEngineLogoFromUrl('http://bing.com');
        $alexaLogo = Piwik_getSearchEngineLogoFromUrl('http://alexa.com');
        $dmozLogo = Piwik_getSearchEngineLogoFromUrl('http://dmoz.org');
        $linkToMajestic = Piwik_SEO_MajesticClient::getLinkForUrl($url);
          
        $majesticMetadata = array(
            'logo' => Piwik_getSearchEngineLogoFromUrl('http://www.majesticseo.com'),
            'url' => $linkToMajestic,
            'url_tooltip' => Piwik_Translate('SEO_ViewBacklinksOnMajesticSEO')
        );
          
        return array(
            Piwik_SEO::GOOGLE_PAGE_RANK_METRIC_NAME => array('logo' => $googleLogo, 'id' => 'pagerank'),
            Piwik_SEO::GOOGLE_INDEXED_PAGE_COUNT => array('logo' => $googleLogo, 'id' => 'google-index'),
            Piwik_SEO::BING_INDEXED_PAGE_COUNT => array('logo' => $bingLogo, 'id' => 'bing-index'),
            Piwik_SEO::ALEXA_RANK_METRIC_NAME => array('logo' => $alexaLogo, 'id' => 'alexa'),
            Piwik_SEO::DMOZ_METRIC_NAME => array('logo' => $dmozLogo, 'id' => 'dmoz'),
            Piwik_SEO::SITE_AGE_LABEL => array('logo' => 'plugins/SEO/images/whois.png', 'id' => 'domain-age'),
            Piwik_SEO::BACKLINK_COUNT => array_merge($majesticMetadata, array('id' => 'external-backlinks')),
            Piwik_SEO::REFERRER_DOMAINS_COUNT => array_merge($majesticMetadata, array('id' => 'referrer-domains')),
        );
    }
    
    private function addSEOMetadataToRows($table, $metadataToAdd)
    {
        if ($table instanceof Piwik_DataTable_Array) {
            foreach ($table->getArray() as $childTable) {
                $this->addSEOMetadataToRows($childTable, $metadataToAdd);
            }
        } else {
            // turn site_creation into age
            $row = $table->getRowFromLabel('site_creation');
            if ($row) {
                $prettyAge = Piwik::getPrettyTimeFromSeconds(time() - $row->getColumn('value'));
                
                $newRow = new Piwik_DataTable_Row();
                $newRow->setColumn('label', Piwik_SEO::SITE_AGE_LABEL);
                $newRow->setColumn('value', $prettyAge);
                
                $table->deleteRow($table->getRowIdFromLabel('site_creation'));
                $table->addRow($newRow);
            }
            
            foreach ($metadataToAdd as $label => $metadata) {
                $row = $table->getRowFromLabel($label);
                
                if ($row) {
                    foreach ($metadata as $name => $value) {
                        $row->setMetadata($name, $value);
                    }
                }
            }
        }
    }
     
    private function translateSEOMetricLabels($table)
    {
        $translateSeoMetricName = array('Piwik_SEO', 'translateSeoMetricName');
        $table->filter('ColumnCallbackReplace',
            array('label', $translateSeoMetricName, array(Piwik_SEO::$seoMetricTranslations)));
    }
}
