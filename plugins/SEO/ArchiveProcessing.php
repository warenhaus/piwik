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
 * TODO
 */
class Piwik_SEO_ArchiveProcessing extends Piwik_ArchiveProcessing
{
    /** Name of a custom RankChecker class to use. Used for testing this plugin. */
    public static $customRankCheckerClassName = null;
    
    /**
     * TODO
     */
    private $rankChecker = null;
    
    /**
     * TODO
     */
    private $stats = null;
    
    /**
     * TODO
     */
    private $doArchiving = false;
    
    /**
     * TODO
     */
    public function __construct($idSite = false, $date = false) // TODO: note $date is only for debugging
    {
        parent::__construct();
        
        if ($idSite !== false
            && $date !== false
        ) {
            $site = new Piwik_Site($idSite);
            $this->setSite($site);
            $this->setPeriod(Piwik_Period::factory('day', $date));
            $this->setSegment(new Piwik_Segment(false, array($idSite)));
            
            $this->init();
            $this->setRequestedPlugin('SEO');
        }
    }
    
    /**
     * TODO
     */
    public function enableArchiving()
    {
        $this->doArchiving = true;
    }

    protected function compute()
    {
        if (!$this->doArchiving) {
            return;
        }
        
        $this->rankChecker = self::makeRankChecker($this->site->getMainUrl(), $this->period->getDateEnd());
        
        $this->stats = $this->rankChecker->getAllStats();
        foreach ($this->stats as $name => $value) {
            $this->insertNumericRecord($name, $value);
        }
    }

    /**
     * TODO
     */
    public function isThereSomeVisits()
    {
        return true;
    }

    /**
     * TODO
     */
    public function launchArchiving()
    {
        parent::launchArchiving();
        return $this->stats;
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
}
