<section class="featured-products">
    <h1>{l s='Our Products'}</h1>
    <div class="products">
        {foreach from=$products item="product"}
            {include file="catalog/product-miniature.tpl" product=$product}
        {/foreach}
    </div>
</section>
