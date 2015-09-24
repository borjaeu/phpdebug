$().ready(function(){
    var oDiagram = new Diagram(10, 60, oSteps, oNamespaces);
});

var Diagram = function(nX, nY, oSteps, oNamespaces) {
    var nWidth, nHeight, oPaper,
        SCALE_X = 40, SCALE_Y = 25, MARGIN_TOP = 200;

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
            oPaper.line(nX, MARGIN_TOP, nX, nHeight, '#000').attr({
                'stroke-dasharray': '-',
                'stroke-width': 1
            });
            oPaper.rotatedText(nX, MARGIN_TOP - 5, shortenNamespace(sKey)).attr({
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
        var sKey, nX0, nX1, nTop = 1, nMiddle, sSource, sTarget;

        for(sKey in oSteps) {
            nX = scaleX(oNamespaces[sKey]);
            sSource = oSteps[sKey]['source'];
            sTarget = oSteps[sKey]['namespace'];
            nX0 = scaleX(oNamespaces[sSource]);
            nX1 = scaleX(oNamespaces[sTarget]);

            if (oSteps[sKey]['type'] == 1) {
                loadCall(nX0, nX1, scaleY(nTop), sKey);
            } else {
                loadResponse(nX0, nX1, scaleY(nTop), sKey);
            }


           /* oPaper.arrow(nX0, scaleY(nTop), nX1, scaleY(nTop), '#F00').attr({
                'stroke-width': 2
            });
            oPaper.text(nMiddle, scaleY(nTop) - 7, oSteps[sKey]['method'] + '()').attr({
                'font-size': 14,
                'cursor': 'pointer'
            }).hover(function() {
                this.attr({fill: '#F00'});
            },function() {
                this.attr({fill: '#000'});
            }).click(function(sKey) {
                return function() {
                    console.log(oSteps[sKey]);
                    $('#call').html(oSteps[sKey]['namespace'] + '::' + oSteps[sKey]['method'] + '()');
                    $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
                    $('#source').attr('href', 'codebrowser:' + oSteps[sKey].path);
                    $('#info').attr('href', oSteps[sKey].path);
                }
            }(sKey));*/
            nTop++;
        }
    };

    var loadCall = function(nX0, nX1, nY, sKey) {
        oPaper.arrow(nX0, nY, nX1, nY, '#F00').attr({
            'stroke-width': 2
        });
        oPaper.text(Math.abs((nX0 + nX1)/2), nY - 7, oSteps[sKey]['method'] + '()').attr({
            'font-size': 14,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() {
                $('#call').html(oSteps[sKey]['namespace'] + '::' + oSteps[sKey]['method'] + '()');
                $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
                $('#source').attr('href', 'codebrowser:' + oSteps[sKey].path);
                $('#info').attr('href', oSteps[sKey].path);
            }
        }(sKey));
    };

    var loadResponse = function(nX0, nX1, nY, sKey) {
        oPaper.arrow(nX0, nY, nX1, nY, '#F00').attr({
            'stroke-width': 2,
            'stroke-dasharray': '-'
        });
        oPaper.text(Math.abs((nX0 + nX1)/2), nY - 7, 'return').attr({
            'font-size': 14,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() {
                $('#call').html('');
                $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
                $('#source').attr('href', 'codebrowser:' + oSteps[sKey].path);
                $('#debug').html(oSteps[sKey]['response']);
            }
        }(sKey));
    };

    nHeight = (getSize(oSteps) + 1) * SCALE_Y + MARGIN_TOP;
    nWidth = getSize(oNamespaces) * SCALE_X + 40;

    oPaper = new Paper(nX, nY, nWidth, nHeight);

    loadClasses();
    loadSteps();
};

var Paper = function(nX, nY, nWidth, nHeight) {
    oPaper = Raphael(nX, nY, nWidth, nHeight);
    oPaper.rect(0, 0, nWidth, nHeight);

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