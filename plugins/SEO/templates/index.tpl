<div id='seo-ranks'>
	<script type="text/javascript" src="plugins/SEO/templates/rank.js"></script>
	
	<div style="padding: 8px;" >
	{* TODO Check if should remove SetupWebSiteURL/SEO_Rank *}
	   <div id="rankStats" align="left">
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
	   				<td>{if !empty($rank.logo_link)}<a href="{$rank.logo_link}" target="_blank" {if !empty($rank.logo_tooltip)}title="{$rank.logo_tooltip}"{/if}>{/if}{if isset($rank.logo)}<img style='vertical-align:middle;margin-right:6px;' src='{$rank.logo}' border='0' alt="{$rank.label}">{/if}{if !empty($rank.logo_link)}</a>{/if} {$rank.label}
	   				</td><td>
	   					<div style='margin-left:15px'>
		   					{if isset($rank.value)}{$rank.value}{else}-{/if}
		   					{if $rank.id=='pagerank'} /10 
		   					{elseif $rank.id=='google-index' || $rank.id=='bing-index'} {'SEO_Pages'|translate}
		   					{/if}
	   					</div>	
   					</td>
	   			</tr>
	   			{/if}
	   			{/foreach}
	   			
	   			</table>
	   		{/if}
	   </div>
	</form>
	<div id="seo-widget-footer" class="form-description" style="text-align:center;margin-top:1em">
		{'SEO_ShowingStatsFor'|translate:$prettyDate} <a class="row-evolution-link" title="View evolution of SEO statistics for this site" data-popover="{$popoverParam}">
			<img src="themes/default/images/row_evolution.png"/>
			<img src="themes/default/images/row_evolution_hover.png" style="display:none"/>
		</a>
	</div>
</div>
{literal}
<script type="text/javascript">
$(document).ready(function() {
	var rowEvolutionLink = $('#seo-ranks .row-evolution-link'),
		popoverUrl = window.location.href + '&popover=' + rowEvolutionLink.attr('data-popover');
	// TODO: use this one: DataTable_RowActions_RowEvolution.launch = function(apiMethod, label)
	rowEvolutionLink.hover(
		function() {
			$('img', this).each(function() {
				$(this).toggle();
			});
		}
	).click(function() {
	    DataTable_RowActions_RowEvolution.launch('SEO.getSEOStats', );
	});
});
</script>
{/literal}
