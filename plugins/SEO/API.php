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
     * Returns SEO Stats in one row without any metadata. Does not return the
     * age of a website.
     * 
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @return Piwik_DataTable
     */
    public function getSEOStatsWithoutMetadata($idSite, $period, $date)
    {
        if ($period == 'range') {
            $oPeriod = new Piwik_Period_Range($period, $date);
              
            $period = 'day';
            $date = $oPeriod->getDateEnd()->toString();
        }
        
        $archive = Piwik_Archive::build($idSite, $period, $date);
        $archive->performQueryWhenNoVisits();
        
        $result = $archive->getDataTableFromNumeric(Piwik_SEO::$seoMetrics);
        $result->filter('ColumnCallbackAddColumn', array(array(), 'label', 'Piwik_Translate', array('SEO_Stats')));
        return $result;
    }
    
    /**
     * Returns SEO stats and site age with metadata (including the logo and any
     * pertinent links).
     * 
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @return Piwik_DataTable
     */
    public function getSEOStats( $idSite, $period, $date )
    {
        $result = $this->getSEOStatsWithoutMetadata($idSite, $period, $date);
        $result->filter('ColumnDelete', array('label'));
        
        // add row for site birth (only if $result is a table)
        if ($result instanceof Piwik_DataTable) {
            $siteBirth = $this->getSiteBirthTime($idSite);
            
            $row = $result->getFirstRow();
            $row->setColumn('site_birth', $siteBirth);
        }
        
        $result = $this->splitColumnsIntoRows($result);
        $this->addSEOMetadataToStatsTable($result, Piwik_Site::getMainUrlFor($idSite));
        $this->translateSEOMetricLabels($result);
        return $result;
    }
    
    /**
     * Returns the birth time of a website's domain in seconds since the epoch.
     * 
     * @param int $idSite
     * @return int
     */
    public function getSiteBirthTime($idSite)
    {
        $siteBirthOption = Piwik_SEO::getSiteBirthOptionName($idSite);
        $siteBirthTime = Piwik_GetOption($siteBirthOption);
        
        if ($siteBirthTime === false) {
            $rank = new Piwik_SEO_RankChecker(Piwik_Site::getMainUrlFor($idSite));
            
            $siteAge = $rank->getAge($prettyFormatAge = false);
            $siteBirthTime = time() - $siteAge;
            
            Piwik_SetOption($siteBirthOption, $siteBirthTime);
        }
        
        return $siteBirthTime;
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
        
        $rankChecker = new Piwik_SEO_RankChecker($url);
        $stats = $rankChecker->getAllStats();
        $stats['site_age'] = $rankChecker->getAge($prettyFormatAge = true);
        
        $dataTable = new Piwik_DataTable();
        $dataTable->addRowsFromArrayWithIndexLabel($stats);
        $this->addSEOMetadataToStatsTable($dataTable, $url);
        $this->translateSEOMetricLabels($dataTable);
        return $dataTable;
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
        // set metadata for individual rows
        $googleLogo = Piwik_getSearchEngineLogoFromUrl('http://google.com');
        $bingLogo = Piwik_getSearchEngineLogoFromUrl('http://bing.com');
        $alexaLogo = Piwik_getSearchEngineLogoFromUrl('http://alexa.com');
        $dmozLogo = Piwik_getSearchEngineLogoFromUrl('http://dmoz.org');
        $linkToMajestic = Piwik_SEO_MajesticClient::getLinkForUrl($url);
          
        $majesticMetadata = array(
            'logo' => 'plugins/SEO/images/majesticseo.png',
            'url' => $linkToMajestic,
            'url_tooltip' => Piwik_Translate('SEO_ViewBacklinksOnMajesticSEO')
        );
          
        $metadataToAdd = array(
            Piwik_SEO::GOOGLE_PAGE_RANK_METRIC_NAME => array('logo' => $googleLogo, 'id' => 'pagerank'),
            Piwik_SEO::GOOGLE_INDEXED_PAGE_COUNT => array('logo' => $googleLogo, 'id' => 'google-index'),
            Piwik_SEO::BING_INDEXED_PAGE_COUNT => array('logo' => $bingLogo, 'id' => 'bing-index'),
            Piwik_SEO::ALEXA_RANK_METRIC_NAME => array('logo' => $alexaLogo, 'id' => 'alexa'),
            Piwik_SEO::DMOZ_METRIC_NAME => array('logo' => $dmozLogo, 'id' => 'dmoz'),
            Piwik_SEO::SITE_AGE_LABEL => array('logo' => 'plugins/SEO/images/whois.png', 'id' => 'domain-age'),
            Piwik_SEO::BACKLINK_COUNT => array_merge($majesticMetadata, array('id' => 'external-backlinks')),
            Piwik_SEO::REFERRER_DOMAINS_COUNT => array_merge($majesticMetadata, array('id' => 'referrer-domains')),
        );
        
        $this->addSEOMetadataToRows($table, $metadataToAdd);
    }
    
    private function addSEOMetadataToRows($table, $metadataToAdd)
    {
        if ($table instanceof Piwik_DataTable_Array) {
            foreach ($table->getArray() as $childTable) {
                $this->addSEOMetadataToRows($childTable, $metadataToAdd);
            }
        } else {
            // turn site_birth into age
            $row = $table->getRowFromLabel('site_birth');
            if ($row) {
                $prettyAge = Piwik::getPrettyTimeFromSeconds(time() - $row->getColumn('value'));
                
                $row->setColumn('label', 'site_age');
                $row->setColumn('value', $prettyAge);
            }
            
            $table->rebuildIndex();
            
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
        $translateSeoMetricName = array('Piwik_SEO_API', 'translateSeoMetricName');
        $table->filter('ColumnCallbackReplace', array('label', $translateSeoMetricName, array(Piwik_SEO::$seoMetricTranslations)));
    }
    
    /**
     * Translates SEO metric name using a set of translations. Used as datatable
     * filter callback.
     * 
     * @ignore
     */
    public static function translateSeoMetricName( $metricName, $translations )
    {
        if (isset($translations[$metricName])) {
            return Piwik_Translate($translations[$metricName]);
        }
        return $metricName;
    }
}
