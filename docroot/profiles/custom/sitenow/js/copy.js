/**
 * @file
 * Sitenow copy script. Attached to every page.
 */

(function ($, Drupal) {
  Drupal.behaviors.copy = {
    attach: function (context, setting) {
      $(".copy-clipboard", context).once('copy').each(function () {
        const text = this.innerText;

        this.innerHTML += '' +
          '<button class="bttn--copy-clipboard">' +
            '<i role="presentation" class="fas fa-clipboard"></i>' +
          '</button>'
        ;

        const delay = 1200;
        const button = $(this).find('.bttn--copy-clipboard')[0];
        button.onmousedown = function() {
          copyToClipboard(text);
          const timeStamp = Date.now();

          button.classList.forEach(function(buttonClass){
            if (buttonClass.startsWith('copy-tooltip-')){
              button.classList.remove(buttonClass);
            }
          });
          button.classList.add('copy-tooltip-' + timeStamp);

          window.rtimeOut(()=>{
            button.classList.forEach(function(buttonClass){
              if (buttonClass.startsWith('copy-tooltip-')){
                const addTime = Number(buttonClass.replace('copy-tooltip-', ''));

                if (Date.now() >= (addTime + delay)) {
                  button.classList.remove(buttonClass);
                }

              }
            });
          }, delay);
        };

        function copyToClipboard(String) {
          navigator.clipboard.writeText(String);
        }

        window.rtimeOut=function(callback,delay){
          var dateNow=Date.now,
            requestAnimation=window.requestAnimationFrame,
            start=dateNow(),
            stop,
            timeoutFunc=function(){
              dateNow()-start<delay?stop||requestAnimation(timeoutFunc):callback()
            };
          requestAnimation(timeoutFunc);
          return{
            clear:function(){stop=1}
          }
        }
      });
    }
  };
})(jQuery, Drupal);
