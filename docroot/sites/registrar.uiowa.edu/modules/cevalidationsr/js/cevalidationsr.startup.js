//--https://www.drupal.org/docs/8/theming/adding-stylesheets-css-and-javascript-js-to-a-drupal-8-theme

(function(Drupal, $, once) {
    /**
     * Apply the mask effect to the elements only once per page load.
     */
    Drupal.behaviors.cevalidationsr = {
        attach(context) 
        {
           // const $elements = $(once('#CeDiD','[data-CeDiD]',context));
           // $elements[0].mask("****-****-****");
            //$('#CeDiD', context).once('#CeDiD').mask("****-****-****");
           
        }
    };
})( Drupal,jQuery,once);