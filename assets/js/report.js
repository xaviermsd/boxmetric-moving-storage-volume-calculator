/**
 * Moving & Storage Volume Calculator: lead report. "Save as PDF" opens the
 * browser's print dialog, where the report can be saved as a PDF.
 *
 * @package MSVCalc
 */
( function () {
	'use strict';
	document.querySelectorAll( '[data-msvcalc-print]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			window.print();
		} );
	} );
	window.addEventListener( 'load', function () {
		window.setTimeout( function () {
			window.print();
		}, 400 );
	} );
}() );
