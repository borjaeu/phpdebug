var oDiagram;

$().ready(function() {
    oDiagram = new Diagram();
    oDiagram.render(10, 60, oSequenceSteps, oSequenceNamespaces);
    $('#delete').on('click', function (oEvent) {
        oEvent.preventDefault();
        oDiagram.deleteSelected();
    });
});

var Diagram = function() {
    var nWidth, nHeight, oCanvas, sSelected, oSteps, oNamespaces,
        SCALE_X = 80, SCALE_Y = 25, MARGIN_TOP = 200, oHistory = {};

    this.render = function(nX, nY, oParamSteps, oParamNamespaces) {
        oSteps = oParamSteps;
        oNamespaces = oParamNamespaces;
        nHeight = (getSize(oSteps) + 1) * SCALE_Y + MARGIN_TOP;
        nWidth = getSize(oNamespaces) * SCALE_X + 40;

        oCanvas = new Paper(nX, nY, nWidth, nHeight);

        loadSteps();

        renderInfo();
        renderNamespacesColumns();
        renderMethods();
        renderArrows();
    };

    var getSize = function(oObject) {
        var nSize = 0, sKey;
        for (sKey in oObject) {
            nSize++;
        }
        return nSize;
    };

    this.deleteSelected = function() {
        $.ajax({
            dataType: "json",
            url: '?sequence=' + sId + '&operation=delete&step=' + sSelected,
            success: function(oData) {
                $('svg').remove();
                oDiagram.render(10, 60, oData.steps, oData.namespaces);
            }
        });
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
            if (oSteps[sKey].type == 2) {
                var sOrigin = oSteps[sKey].from, nY0;
                nY0 = oHistory[sOrigin];
                oCanvas.rect(oSteps[sKey].nX0 - 5, nY0 - 5, 10, oSteps[sKey].nY - nY0 + 10).attr({'fill': '#FFF'});
            } else if (oSteps[sKey].type == 3) {
                oCanvas.rect(oSteps[sKey].nX1 - 5, oSteps[sKey].nY - 10, 10, 20).attr({'fill': '#FFF'});
            } else if (typeof oSteps[sKey].end == 'undefined') {
                oCanvas.rect(oSteps[sKey].nX1 - 5, oSteps[sKey].nY - 10, 10, nHeight).attr({'fill': '#FFF'});
            }
        }
    };

    var renderArrows = function() {
        for(var sKey in oSteps) {
            if (oSteps[sKey]['type'] == 1) {
                renderItemCallArrow(sKey);
            } else if (oSteps[sKey]['type'] == 3) {
                renderItemCallAndResponseArrow(sKey);
            } else if (oSteps[sKey]['type'] == 2) {
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
        oCanvas.text(Math.abs((oSteps[sKey].nX0 + oSteps[sKey].nX1)/2), oSteps[sKey].nY - 7, oSteps[sKey]['method']).attr({
            'font-size': 12,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() { updatePanel(sKey); }
        }(sKey));
    };

    /**
     * Load call arrows
     *
     * @param sKey
     */
    var renderItemCallAndResponseArrow = function(sKey) {
        oCanvas.arrow(oSteps[sKey].nX0, oSteps[sKey].nY, oSteps[sKey].nX1, oSteps[sKey].nY, '#F00').attr({
            'stroke-width': 2,
            'arrow-start': 'classic-wide-long'
        });
        oCanvas.text(Math.abs((oSteps[sKey].nX0 + oSteps[sKey].nX1)/2), oSteps[sKey].nY - 7, oSteps[sKey]['method']).attr({
            'font-size': 12,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() { updatePanel(sKey); }
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
        oCanvas.text(Math.abs((oSteps[sKey].nX0 + oSteps[sKey].nX1)/2), oSteps[sKey].nY - 7, oSteps[sKey]['response']).attr({
            'font-size': 12,
            'cursor': 'pointer'
        }).hover(function() {
            this.attr({fill: '#F00'});
        },function() {
            this.attr({fill: '#000'});
        }).click(function(sKey) {
            return function() { updatePanel(sKey); }
        }(sKey));
    };

    /**
     * Updates info panel with selected call info
     *
     * @param string sKey Identifier of the step
     */
    var updatePanel = function(sKey) {
        $('#main_panel').removeClass('hidden');

        sSelected = sKey;
        if (oSteps[sKey]['type'] != 2) {
            $('#call').html(oSteps[sKey]['namespace'] + '::' + oSteps[sKey]['method'] + '()');
            $('#source a').attr('href', 'codebrowser:' + oSteps[sKey].path).removeClass('hidden');
            $('#source').removeClass('hidden');
        } else {
            $('#source').addClass('hidden');
        }
        $('#info').attr('href', oSteps[sKey].path);
        $('#json').attr('href', 'codebrowser:' + sFile + '->' + sKey);
    };

    var renderItemInfo = function(sKey) {
        var sOrigin = oSteps[sKey].from;

        nY0 = oHistory[sOrigin];

        if (oSteps[sKey].info == '') {
            return;
        }

        oPaper.rect(0, oSteps[sKey].nY - (SCALE_Y / 2) - 1, nWidth, SCALE_Y - 2).attr({
            'fill': '#00F',
            'stroke': '#FFF',
            'opacity':.3
        });

        oPaper.text(nWidth - 10, oSteps[sKey].nY, oSteps[sKey].info).attr({
            'text-anchor':'end',
            'font-size': 14
        });
    };
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