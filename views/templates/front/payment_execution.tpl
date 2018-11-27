{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='netcents'}">
        {l s='Checkout' mod='netcents'}
    </a>
    <span class="navigation-pipe">{$navigationPipe|escape:'htmlall':'UTF-8'}</span>
    {l s='NetCents payment' mod='netcents'}
{/capture}

<h1 class="page-heading">
    {l s='Order summary' mod='netcents'}
</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="alert alert-warning">
        {l s='Your shopping cart is empty.' mod='netcents'}
    </p>
{else}
    <form action="{$link->getModuleLink('netcents', 'redirect', [], true)|escape:'html':'UTF-8'}" method="post">
        <div class="box cheque-box">
            <h3 class="page-subheading">
                {l s='NetCents payment' mod='netcents'}
            </h3>

            <p class="cheque-indent">
                <strong class="dark">
                    {l s='You have chosen to pay with Cryptocurrency via NetCents.' mod='netcents'} {l s='Here is a short summary of your order:' mod='netcents'}
                </strong>
            </p>

            <p>
                - {l s='The total amount of your order is' mod='netcents'}
                <span id="amount" class="price">{displayPrice price=$total}</span>
                {if $use_taxes == 1}
                    {l s='(tax incl.)' mod='netcents'}
                {/if}
            </p>

            <p>
                - {l s='You will be redirected to NetCents for payment with Cryptocurrency.' mod='netcents'}
                <br/>
                - {l s='Please confirm your order by clicking "I confirm my order".' mod='netcents'}
            </p>
        </div>
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='netcents'}
            </a>
            <button class="button btn btn-default button-medium" type="submit">
                <span>{l s='I confirm my order' mod='netcents'}<i class="icon-chevron-right right"></i></span>
            </button>
        </p>
    </form>
{/if}
