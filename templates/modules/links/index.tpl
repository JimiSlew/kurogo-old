{include file="findInclude:common/header.tpl"}

{if isset($moduleStrings.description) && strlen($moduleStrings.description)}
  <p class="{block name='headingClass'}nonfocal smallprint{/block}">
    {$moduleStrings.description|escape}
  </p>
{/if}

{if $display_type=='springboard'}
  {include file="findInclude:common/springboard.tpl" springboardItems=$links springboardID="links"}
{elseif $display_type=='list'}
  {include file="findInclude:common/navlist.tpl" navlistItems=$links}
{/if}

<p class="clear"> </p>

{include file="findInclude:common/footer.tpl"}
