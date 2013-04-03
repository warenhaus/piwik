<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Tests use of custom variable segments.
 */
class Test_Piwik_Integration_TwoVisitsWithCustomVariables_SegmentContains extends IntegrationTestCase
{
    public static $fixture = null; // initialized below class definition

    public function getOutputPrefix()
    {
        return 'twoVisitsWithCustomVariables';
    }
    
    public function setUp()
    {
        Piwik_TaskScheduler::setRunning(true);
        Piwik_Config::getInstance()->General['seo_max_sites_to_archive_metrics_for'] = 0;
    }
    
    public function tearDown()
    {
        Piwik_TaskScheduler::setRunning(false);
        Piwik_Config::getInstance()->General['seo_max_sites_to_archive_metrics_for'] = 100;
    }

    /**
     * @dataProvider getApiForTesting
     * @group        Integration
     * @group        TwoVisitsWithCustomVariables_SegmentContains
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        $idSite = self::$fixture->idSite;
        $dateTime = self::$fixture->dateTime;

        $return = array();

        $api = array('Actions.getPageUrls', 'Actions.getPageTitles', 'VisitsSummary.get');
        $segmentsToTest = array(
            // array( SegmentString , TestSuffix , Array of API to test)
            array("pageTitle=@*_)%", '_SegmentPageTitleContainsStrangeCharacters', array('Actions.getPageTitles', 'VisitsSummary.get')),
            array("pageUrl=@user/profile", '_SegmentPageUrlContains', $api),
            array("pageTitle=@Profile pa", '_SegmentPageTitleContains', $api),
            array("pageUrl!@user/profile", '_SegmentPageUrlExcludes', $api),
            array("pageTitle!@Profile pa", '_SegmentPageTitleExcludes', $api),
        );

        foreach ($segmentsToTest as $segment) {
            // Also test "Page URL / Page title CONTAINS string" feature
            $return[] = array($segment[2],
                              array('idSite'       => $idSite, 'date' => $dateTime, 'periods' => array('day'),
                                    'setDateLastN' => false,
                                    'segment'      => $segment[0],
                                    'testSuffix'   => $segment[1])
            );
        }
        
        // test SEO API with max_sites to archive for = 0
        $seoApi = 'SEO.getSEOStats';
        $return[] = array($seoApi, array('idSite'       => 'all',
                                         'date'         => $dateTime,
                                         'periods'      => 'day',
                                         'setDateLastN' => true,
                                         'testSuffix'   => '_LastPeriods'));
        $return[] = array($seoApi, array('idSite'                 => 'all',
                                         'date'                   => $dateTime,
                                         'periods'                => 'day',
                                         'setDateLastN'           => true,
                                         'testSuffix'             => '_LastPeriods_full',
                                         'otherRequestParameters' => array('full' => 1)));
        
        return $return;
    }
}

Test_Piwik_Integration_TwoVisitsWithCustomVariables_SegmentContains::$fixture
    = new Test_Piwik_Fixture_TwoVisitsWithCustomVariables();
Test_Piwik_Integration_TwoVisitsWithCustomVariables_SegmentContains::$fixture->doExtraQuoteTests = false;

