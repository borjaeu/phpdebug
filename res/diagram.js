$().ready(function(){
    console.log(oSteps);
    console.log(oNamespaces);

    var oDiagram = new Diagram(10, 60, oSteps, oNamespaces);
});


var Diagram = function(nX, nY, oSteps, oNamespaces) {
    var nWidth, nHeight, oPaper,
        SCALE_X = 40, SCALE_Y = 15, MARGIN_TOP = 200;

    var getSize = function(oObject) {
        var nSize = 0, sKey;
        for (sKey in oObject) {
            nSize++;
        }
        return nSize;
    };

    /**
     * Scales the horizontal coordinates
     *
     * @param nX
     * @returns {number}
     */
    var scaleX = function(nX){
        return nX * SCALE_X + (SCALE_X / 2);
    };

    /**
     * Scales the horizontal coordinates
     *
     * @param nY
     * @returns {number}
     */
    var scaleY = function(nY){
        return nY * SCALE_Y + (SCALE_Y / 2) + MARGIN_TOP;
    };

    /**
     * Loads the columns for the classes
     */
    var loadClasses = function() {
        var sKey, nX;

        for(sKey in oNamespaces) {
            nX = scaleX(oNamespaces[sKey]);
            oPaper.line(nX, MARGIN_TOP, nX, nHeight, '#000');
            oPaper.rotatedText(nX, MARGIN_TOP - 5, shortenNamespace(sKey));
        }
    };

    var shortenNamespace = function(sNamespace) {
        var aChunks;

        aChunks = sNamespace.split('\\');
        sNamespace = aChunks.slice(-2).join('\\');
        return sNamespace;
    };

    var loadSteps = function() {
        var sKey, nX0, nX1, nTop = 1, nMiddle, sSource, sTarget;

        for(sKey in oSteps) {
            nX = scaleX(oNamespaces[sKey]);
            sSource = oSteps[sKey]['source'];
            sTarget = oSteps[sKey]['namespace'];
            nX0 = scaleX(oNamespaces[sSource]);
            nX1 = scaleX(oNamespaces[sTarget]);

            nMiddle = Math.abs(nX0 - nX1);

            oPaper.arrow(nX0, scaleY(nTop), nX1, scaleY(nTop), '#F00').hover(function(sKey) {
                return function() { console.log(sKey)}
            });
            oPaper.text(nMiddle, scaleY(nTop) - 5, oSteps[sKey]['method'], '#F0F');
            nTop++;
        }
    };

    nHeight = getSize(oSteps) * SCALE_Y + MARGIN_TOP;
    nWidth = getSize(oNamespaces) * SCALE_X;

    oPaper = new Paper(nX, nY, nWidth, nHeight);

    loadClasses();
    loadSteps();
};

var Paper = function(nX, nY, nWidth, nHeight) {
    oPaper = Raphael(nX, nY, nWidth, nHeight);
    oPaper.rect(0, 0, nWidth, nHeight);

    this.line = function(nX0, nY0, nX1, nY1, sColor) {
        var sPath = 'M' + nX0 + ' ' + nY0 + 'L' + nX1 + ' ' + nY1;

        return oPaper.path(sPath).attr({stroke: sColor});
    };

    this.arrow = function(nX0, nY0, nX1, nY1, sColor) {
        return this.line(nX0, nY0, nX1, nY1, sColor).attr({ 'arrow-end': 'classic-wide-long'});
    };

    this.text = function(nX, nY, sText) {
        return oPaper.text(nX, nY, sText)
    };

    this.rotatedText = function(nX, nY, sText) {
        return this.text(nX, nY, sText).attr({transform: "r300"}).attr({'text-anchor':'start'});
    };
};