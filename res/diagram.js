$().ready(function(){
    var oDiagram = new Diagram(10, 60, oSteps, oNamespaces);
});

var Diagram = function(nX, nY, oSteps, oNamespaces) {
    var nWidth, nHeight, oCanvas,
        SCALE_X = 80, SCALE_Y = 25, MARGIN_TOP = 200, oHistory = {};

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
    var renderNamespacesColumns = function() {
        var sKey, nX;

        for(sKey in oNamespaces) {
            nX = scaleX(oNamespaces[sKey]);
            oCanvas.line(nX, MARGIN_TOP, nX, nHeight, '#000').attr({
                'stroke-dasharray': '-',
                'stroke-width': 1
            });
            oCanvas.rotatedText(nX, MARGIN_TOP - 5, shortenNamespace(sKey)).attr({
                'font-size': 14
            });
        }
    };

    /**
     * Get smaller namespace size by keeping last 2 levels
     *
     * @param sNamespace
     * @returns {string|*}
     */
    var shortenNamespace = function(sNamespace) {
        var aChunks;

        aChunks = sNamespace.split('\\');
        sNamespace = aChunks.slice(-2).join('\\');
        return sNamespace;
    };

    var loadSteps = function() {
        var sKey, nTop = 1, sSource, sTarget;

        for(sKey in oSteps) {
            sSource = oSteps[sKey]['source'];
            sTarget = oSteps[sKey]['namespace'];

            oSteps[sKey].nX0 = scaleX(oNamespaces[sSource]);
            oSteps[sKey].nX1 = scaleX(oNamespaces[sTarget]);
            oSteps[sKey].nY = scaleY(nTop);

            oHistory[sKey] = oSteps[sKey].nY;
            nTop++;
        }
    };

    var renderMethods = function() {
        var sKey;

        for(sKey in oSteps) {
            renderItemResponseMethod(sKey);
        }
    };

    var renderArrows = function() {
        for(var sKey in oSteps) {
            if (oSteps[sKey]['type'] == 1) {
                renderItemCallArrow(sKey);
            } else {
                renderItemResponseArrow(sKey);
            }
        }
    };

    var renderInfo = function() {
        for(var sKey in oSteps) {
            renderItemInfo(sKey);
        }
    };

    /**
     * Load call arrows
     *
     * @param sKey
     */
    var renderItemCallArrow = function(sKey) {
        oCanvas.arrow(oSteps[sKey].nX0, oSteps[sKey].nY, oSteps[sKey].nX1, oSteps[sKey].nY, '#F00').attr({
            'stroke-width': 2
        });
        oCanvas.text(Math.abs((oSteps[sKey].nX0 + oSteps[sKey].nX1)/2), oSteps[sKey].nY - 7, oSteps[sKey]['method'] + '()').attr({
            'font-size': 12,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() {
                $('#main_panel').removeClass('hidden');
                $('#call').html(oSteps[sKey]['namespace'] + '::' + oSteps[sKey]['method'] + '()');
                $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
                $('#source').attr('href', 'codebrowser:' + oSteps[sKey].path);
                $('#info').attr('href', oSteps[sKey].path);
            }
        }(sKey));
    };

    /**
     * Loads arrow for a response
     *
     * @param sKey
     */
    var renderItemResponseArrow = function(sKey) {
        oCanvas.arrow(oSteps[sKey].nX0, oSteps[sKey].nY, oSteps[sKey].nX1, oSteps[sKey].nY, '#F00').attr({
            'stroke-width': 2,
            'stroke-dasharray': '-'
        });
        oCanvas.text(Math.abs((oSteps[sKey].nX0 + oSteps[sKey].nX1)/2), oSteps[sKey].nY - 7, 'return').attr({
            'font-size': 12,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() {
                $('#main_panel').removeClass('hidden');
                $('#call').html('');
                $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
                $('#source').attr('href', 'codebrowser:' + oSteps[sKey].path);
            }
        }(sKey));
    };

    var renderItemInfo = function(sKey) {
        var sOrigin = oSteps[sKey].from;

        nY0 = oHistory[sOrigin];

        if (oSteps[sKey].info == '') {
            return;
        }

        oPaper.rect(0, oSteps[sKey].nY - (SCALE_Y / 2), nWidth, SCALE_Y - 2).attr({
            'fill': '#00F',
            'stroke': '#FFF',
            'opacity':.3
        });

        oPaper.text(nWidth - 10, oSteps[sKey].nY, oSteps[sKey].info).attr({
            'text-anchor':'end',
            'font-size': 14
        });
    };

    /**
     * Renders the information for a method call
     *
     * @param sKey
     */
    var renderItemResponseMethod = function(sKey) {
        var sOrigin = oSteps[sKey].from, nY0;

        nY0 = oHistory[sOrigin];
        oCanvas.rect(oSteps[sKey].nX0 - 5, nY0 - 5, 10, oSteps[sKey].nY - nY0 + 10).attr({'fill': '#FFF'});
    };

    nHeight = (getSize(oSteps) + 1) * SCALE_Y + MARGIN_TOP;
    nWidth = getSize(oNamespaces) * SCALE_X + 40;

    oCanvas = new Paper(nX, nY, nWidth, nHeight);

    loadSteps();

    renderInfo();
    renderNamespacesColumns();
    renderMethods();
    renderArrows();
};

var Paper = function(nX, nY, nWidth, nHeight) {
    oPaper = Raphael(nX, nY, nWidth, nHeight);
    oPaper.rect(0, 0, nWidth, nHeight);

    this.rect = function(nX0, nY0, nX1, nY1) {
        return oPaper.rect(nX0, nY0, nX1, nY1);
    };

    this.line = function(nX0, nY0, nX1, nY1) {
        var sPath = 'M' + nX0 + ' ' + nY0 + 'L' + nX1 + ' ' + nY1;
        return oPaper.path(sPath);
    };

    this.arrow = function(nX0, nY0, nX1, nY1) {
        return this.line(nX0, nY0, nX1, nY1).attr({'arrow-end': 'classic-wide-long'});
    };

    this.text = function(nX, nY, sText) {
        return oPaper.text(nX, nY, sText)
    };

    this.rotatedText = function(nX, nY, sText) {
        return this.text(nX, nY, sText).attr({transform: "r300"}).attr({'text-anchor':'start'});
    };
};