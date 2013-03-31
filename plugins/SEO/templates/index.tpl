<div id='seo-ranks'>
    <script type="text/javascript" src="plugins/SEO/templates/rank.js"></script>
    
    <div style="padding: 8px;" >
        <div align="left" class="mediumtext">
          {'Installation_SetupWebSiteURL'|translate|ucfirst}
          <input type="text" id="seoUrl" size="15" value="{$urlToRank|escape:'html'}" class="textbox"/>
          <span style="padding-left:2px;"> 
              <input type="submit" id="check-site-seo-btn" value="{'General_Check'|translate}"/>
          </span>
          <span id="ajaxLoadingSEO" class='loadingPiwik' style='display:none'><img src='./themes/default/images/loading-blue.gif'/></span>
        </div>
    {* TODO Check if should remove SEO_Rank *}
        <div id="rankStats" align="left" style="margin-top:1em;">
            {if empty($ranks)}
                {'General_Error'|translate}
            {else}
                {capture name=cleanUrl}
                <a href='{$urlToRank|escape:'html'}' target='_blank'>{$urlToRank|escape:'html'}</a>
                {/capture}
                {'SEO_SEORankingsFor'|translate:$smarty.capture.cleanUrl}
                <table cellspacing='2' style='margin:auto;line-height:1.5em;padding-top:10px'>
                {foreach from=$ranks item=rank}
                {if !($rank.id=='dmoz' && $rank.value==0)}{* do not display dmoz metric if value == 0 *}
                <tr>
                    {capture assign=seoLink}<a class="linkContent" href="?module=Proxy&action=redirect&url={$rank.logo_link|urlencode}" target="_blank" {if !empty($rank.logo_tooltip)}title="{$rank.logo_tooltip}"{/if}>{/capture}
                    {capture assign=majesticLink}{$seoLink}Majestic</a>{/capture}
                    <td>{if !empty($rank.logo_link)}<a href="{$rank.logo_link}" target="_blank" {if !empty($rank.logo_tooltip)}title="{$rank.logo_tooltip}"{/if}>{/if}{if isset($rank.logo)}<img style='vertical-align:middle;margin-right:6px;' src='{$rank.logo}' border='0' alt="{$rank.label}">{/if}{if !empty($rank.logo_link)}</a>{/if} {$rank.label}
                    </td><td>
                        <div style='margin-left:15px'>
                            {if !empty($rank.logo_link)}{$seoLink}{/if}
                                {if isset($rank.value)}{$rank.value}{else}-{/if}
                                {if $rank.id=='pagerank'} /10 
                                {elseif $rank.id=='google-index' || $rank.id=='bing-index'} {'SEO_Pages'|translate}
                                {/if}
                            {if !empty($rank.logo_link)}</a>{/if}
                        </div>    
                    </td>
                </tr>
                {/if}
                {/foreach}
                   
                </table>
            {/if}
        </div>
    </div>
    {if !empty($prettyDate)}
    <div id="seo-widget-footer" class="form-description" style="text-align:center;margin-top:1em">
        {'SEO_ShowingStatsFor'|translate:$prettyDate} <a class="row-evolution-link" title="{'SEO_RowEvolutionTooltip'|translate}">
            <img src="themes/default/images/row_evolution.png"/>
            <img src="themes/default/images/row_evolution_hover.png" style="display:none"/>
        </a>
    </div>
    {/if}
</div>
{literal}
<script type="text/javascript">
$(document).ready(function() {// TODO: remove rank.js or move this there?
    $('#seo-ranks .row-evolution-link').hover(
        function() {
            $('img', this).each(function() {
                $(this).toggle();
            });
        }
    ).click(function(e) {
        e.preventDefault();
        DataTable_RowActions_RowEvolution.launch('SEO.getSEOStatsWithoutMetadata', {/literal}"{'SEO_Stats'|translate}"{literal});
        return false;
    });
    
    $('#check-site-seo-btn').click(function() {
        var ajaxRequest = new ajaxHelper();
        ajaxRequest.setLoadingElement('#ajaxLoadingSEO');
        ajaxRequest.addParams({
            module: 'SEO',
            action: 'getSEOStatsForUrl',
            url: encodeURIComponent($('#seoUrl').val())
        }, 'get');
        ajaxRequest.setCallback(
            function (response) {
                $('#seo-ranks').html(response);
            }
        );
        ajaxRequest.setFormat('html');
        ajaxRequest.send(false);
    });
});
</script>
{/literal}
