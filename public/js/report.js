/*
 * Validation report (templates/report.html.twig) : printing with the browser ("Save as PDF").
 * Not inline because of the Content-Security-Policy (script-src 'self').
 */
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('print-button');
    if (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    }
    // ?print : opened from the "PDF report" action of the demo client
    if (new URLSearchParams(window.location.search).has('print')) {
        window.print();
    }
});
