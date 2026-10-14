{**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='My subscriptions' d='Modules.Alliancepay.Shop'}
{/block}

{block name='page_content'}
  {if empty($alliance_subscriptions)}
    <p>{l s='You have no subscriptions yet.' d='Modules.Alliancepay.Shop'}</p>
  {else}
    <table class="table table-striped table-bordered">
      <thead>
        <tr>
          <th>{l s='Plan' d='Modules.Alliancepay.Shop'}</th>
          <th>{l s='Order' d='Modules.Alliancepay.Shop'}</th>
          <th>{l s='Status' d='Modules.Alliancepay.Shop'}</th>
          <th>{l s='Next payment' d='Modules.Alliancepay.Shop'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$alliance_subscriptions item=subscription}
          <tr>
            <td>{$subscription.plan_name|escape:'html':'UTF-8'}</td>
            <td>#{$subscription.order_id|intval}</td>
            <td>{$subscription.status_label|escape:'html':'UTF-8'}</td>
            <td>{if $subscription.next_payment}{$subscription.next_payment|escape:'html':'UTF-8'}{else}&mdash;{/if}</td>
            <td>
              {if $subscription.can_cancel}
                <form method="post" action="{$alliance_subscriptions_url|escape:'html':'UTF-8'}"
                      onsubmit="return confirm('{l s='Cancel this subscription?' d='Modules.Alliancepay.Shop' js=1}');">
                  <input type="hidden" name="token" value="{$alliance_token|escape:'html':'UTF-8'}">
                  <input type="hidden" name="provider_order_id" value="{$subscription.provider_order_id|escape:'html':'UTF-8'}">
                  <button type="submit" name="alliance_cancel_subscription" value="1" class="btn btn-secondary btn-sm">
                    {l s='Cancel subscription' d='Modules.Alliancepay.Shop'}
                  </button>
                </form>
              {/if}
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {/if}
{/block}
