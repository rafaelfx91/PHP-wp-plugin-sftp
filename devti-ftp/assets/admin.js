(function($){
  window.DevTiFTPAreYouSure = function(){
    if (typeof DevTiFTP !== 'undefined' && DevTiFTP.confirmMigrate) {
      return window.confirm(DevTiFTP.confirmMigrate);
    }
    return true;
  };
})(jQuery);
