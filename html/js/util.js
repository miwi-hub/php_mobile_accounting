/*
* Hilfsfunktionen
*/
var util = util || {};

// Ganzzahlige Division
util.intDivision = function(dividend, divisor) { return (dividend/divisor)-(dividend%divisor)/divisor; }

// Datum von 2013-01-01 nach 01.01.2013 umformatieren
util.formatDateAtG = function(dateStringIn) {
    if(dateStringIn.length != 10) return dateStringIn;
    var dateStringOut = dateStringIn.substr(8,2)+"."
                       +dateStringIn.substr(5,2)+"."
                       +dateStringIn.substr(0,4);

    return dateStringOut;
}
