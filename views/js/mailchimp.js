$(document).ready(function () {
    $('#importAll_on').click(function () {
        $(this).closest('.form-group').next().hide();
    });
    $('#importAll_off').click(function () {
        $(this).closest('.form-group').next().show();
    });
    if (typeof($('#importAll_on')) != 'undefined') {
        if ($('#importAll_on').val() == '1') {
            $('#importAll_on').click();
        }
    }
});