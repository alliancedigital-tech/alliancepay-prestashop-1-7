{**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *}
<div class="alliance-subscription-choice">
  {if $alliance_is_logged}
    <div class="alliance-purchase-type">
      <label class="alliance-option">
        <input type="radio" class="alliance-purchase-type-input" name="{$alliance_field_type}" value="one_time" checked>
        {l s='One-time purchase' d='Modules.Alliancepay.Shop'}
      </label>
      <label class="alliance-option">
        <input type="radio" class="alliance-purchase-type-input" name="{$alliance_field_type}" value="subscription">
        {l s='Subscribe' d='Modules.Alliancepay.Shop'}
      </label>
    </div>
    <div class="alliance-plans" style="display: none;">
      {foreach from=$alliance_plans item=plan name=alliancePlans}
        <label class="alliance-plan">
          <input type="radio" name="{$alliance_field_plan}" value="{$plan.id|intval}" {if $smarty.foreach.alliancePlans.first}checked{/if}>
          <strong>{$plan.name|escape:'html':'UTF-8'}</strong>
          <ul class="alliance-plan-stages">
            {foreach from=$plan.stages item=stage}
              <li>
                {l s='Payment' d='Modules.Alliancepay.Shop'} {$stage.payments|escape:'html':'UTF-8'}:
                {if $stage.is_free}{l s='free (trial)' d='Modules.Alliancepay.Shop'}{else}{$stage.amount|escape:'html':'UTF-8'}{/if},
                {l s='every' d='Modules.Alliancepay.Shop'} {$stage.interval|intval}
                {if $stage.unit == 'day'}{l s='day(s)' d='Modules.Alliancepay.Shop'}
                {elseif $stage.unit == 'week'}{l s='week(s)' d='Modules.Alliancepay.Shop'}
                {elseif $stage.unit == 'month'}{l s='month(s)' d='Modules.Alliancepay.Shop'}
                {else}{l s='year(s)' d='Modules.Alliancepay.Shop'}{/if}
              </li>
            {/foreach}
          </ul>
        </label>
      {/foreach}
    </div>
  {else}
    <p class="alliance-subscription-login">
      <a href="{$alliance_login_url|escape:'html':'UTF-8'}">{l s='Sign in to subscribe' d='Modules.Alliancepay.Shop'}</a>
    </p>
  {/if}
</div>
