(function($) {
  Craft.MigrationManagerGlobalsExport = Garnish.Base.extend({
    init: function() {
      $("#main #header #action-buttons").append('<button class="btn" id="create-migration">Create Migration</button>');
      $("button#create-migration").on("click", this.createMigration);
    },

    createMigration: function(evt) {
      $('input[name="action"]').val("migrationassistant/migrations/create-globals-content-migration");
      return true;
    },
  });
})(jQuery);
