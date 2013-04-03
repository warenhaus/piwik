<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

class MockSEORankChecker
{
    public $url;
    
    public $pageRank = 1;
    public $alexaRank = 2;
    public $dmoz = 3;
    public $googleIndexedPages = 4;
    public $bingIndexedPages = 5;
    public $yearsOld = 6;
    public $externalBacklinkCount = 7;
    public $referrerDomainCount = 8;
    
    /**
     * Used to make sure SEO metrics differ based on the archived day.
     */
    public $dateMultiplier = 1;
    
    public function __construct($url, $date = null)
    {
        $this->url = $url;
        
        if ($date) {
            $parts = explode('-', $date);
            $day = (int)$parts[2];
            $this->dateMultiplier = $day;
        }
    }
    
    public function getPageRank()
    {
        return $this->pageRank * $this->dateMultiplier;
    }
    
    public function getAlexaRank()
    {
        return $this->alexaRank * $this->dateMultiplier;
    }
    
    public function getDmoz()
    {
        return $this->dmoz * $this->dateMultiplier;
    }
    
    public function getIndexedPagesGoogle()
    {
        return $this->googleIndexedPages * $this->dateMultiplier;
    }
    
    public function getIndexedPagesBing()
    {
        return $this->bingIndexedPages * $this->dateMultiplier;
    }
    
    public function getAge( $prettyFormatAge = true )
    {
        $birth = mktime(date("H"), date('i'), date('s'), date('n'), date('j'), date('Y') - $this->yearsOld);
        $age = time() - $birth;
        if ($prettyFormatAge) {
            return Piwik::getPrettyTimeFromSeconds($age);
        } else {
            return $age;
        }
    }
    
    public function getExternalBacklinkCount()
    {
        return $this->externalBacklinkCount * $this->dateMultiplier;
    }
    
    public function getReferrerDomainCount()
    {
        return $this->referrerDomainCount * $this->dateMultiplier;
    }
    
    public function getAllStats()
    {
        $result = array(
            Piwik_SEO::GOOGLE_PAGE_RANK_METRIC_NAME => $this->getPageRank(),
            Piwik_SEO::GOOGLE_INDEXED_PAGE_COUNT => $this->getIndexedPagesGoogle(),
            Piwik_SEO::ALEXA_RANK_METRIC_NAME => $this->getAlexaRank(),
            Piwik_SEO::DMOZ_METRIC_NAME => $this->getDmoz(),
            Piwik_SEO::BING_INDEXED_PAGE_COUNT => $this->getIndexedPagesBing(),
            Piwik_SEO::BACKLINK_COUNT => $this->getExternalBacklinkCount(),
            Piwik_SEO::REFERRER_DOMAINS_COUNT => $this->getReferrerDomainCount()
        );
        return $result;
    }
}
