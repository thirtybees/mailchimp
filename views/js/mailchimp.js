$(document).ready(function () {
  $importAllOn = $('#importAll_on');
  $importAllOff = $('#importAll_off');

  $importAllOn.click(function () {
    $(this).closest('.form-group').next().hide();
  });
  $importAllOff.click(function () {
    $(this).closest('.form-group').next().show();
  });

  if (typeof($importAllOn) != 'undefined') {
    if ($importAllOn.attr('checked')) {
      $importAllOn.click();
    }
  }
});
