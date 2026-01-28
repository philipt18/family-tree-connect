/**
 * Family Tree Connect - Chart JavaScript
 */

(function($) {
    'use strict';

    FTC.chart = {
        init: function() {
            var self = this;
            
            $('.ftc-chart').each(function() {
                self.renderChart($(this));
            });
            
            // Toolbar buttons
            $(document).on('click', '.ftc-chart-zoom-in', function() {
                var $chart = $(this).closest('.ftc-chart');
                self.zoom($chart, 1.2);
            });
            
            $(document).on('click', '.ftc-chart-zoom-out', function() {
                var $chart = $(this).closest('.ftc-chart');
                self.zoom($chart, 0.8);
            });
            
            $(document).on('click', '.ftc-chart-fit', function() {
                var $chart = $(this).closest('.ftc-chart');
                self.fitToScreen($chart);
            });
            
            $(document).on('click', '.ftc-chart-export-pdf', function() {
                var $chart = $(this).closest('.ftc-chart');
                self.exportPDF($chart);
            });
            
            // Chart type change
            $(document).on('change', '.ftc-chart-type-select', function() {
                var $chart = $(this).closest('.ftc-chart-wrapper').find('.ftc-chart');
                var chartData = $chart.data('chart');
                chartData.type = $(this).val();
                self.renderChart($chart, chartData);
            });
            
            // Direction change
            $(document).on('change', '.ftc-chart-direction-select', function() {
                var $chart = $(this).closest('.ftc-chart-wrapper').find('.ftc-chart');
                var chartData = $chart.data('chart');
                chartData.direction = $(this).val();
                self.renderChart($chart, chartData);
            });
        },
        
        renderChart: function($container, chartData) {
            var self = this;
            chartData = chartData || JSON.parse($container.attr('data-chart'));
            
            if (!chartData || !chartData.nodes) {
                return;
            }
            
            var $canvas = $container.find('.ftc-chart-canvas');
            $canvas.empty();
            
            // Calculate layout
            var layout = self.calculateLayout(chartData);
            
            // Create SVG
            var svg = self.createSVG(layout, chartData);
            $canvas.html(svg);
            
            // Store data
            $container.data('chart', chartData);
            $container.data('scale', 1);
            
            // Make nodes clickable
            $canvas.find('.ftc-node').on('click', function() {
                var nodeId = $(this).data('id');
                var node = chartData.nodes[nodeId];
                if (node && node.url) {
                    window.location.href = node.url;
                }
            });
            
            // Enable pan
            self.enablePan($container);
        },
        
        calculateLayout: function(chartData) {
            var nodes = chartData.nodes;
            var direction = chartData.direction || 'TB';
            
            var boxWidth = 180;
            var boxHeight = 100;
            var spacingX = 40;
            var spacingY = 60;
            
            // Group by generation
            var generations = {};
            Object.keys(nodes).forEach(function(id) {
                var gen = nodes[id].generation || 0;
                if (!generations[gen]) generations[gen] = [];
                generations[gen].push(id);
            });
            
            var genKeys = Object.keys(generations).map(Number).sort(function(a, b) { return a - b; });
            var isVertical = direction === 'TB' || direction === 'BT';
            
            var positions = {};
            var maxWidth = 0;
            var maxHeight = 0;
            
            genKeys.forEach(function(gen, genIndex) {
                var nodesInGen = generations[gen];
                var count = nodesInGen.length;
                
                nodesInGen.forEach(function(id, i) {
                    var x, y;
                    
                    if (isVertical) {
                        var genWidth = count * boxWidth + (count - 1) * spacingX;
                        x = (i * (boxWidth + spacingX)) - (genWidth / 2) + (boxWidth / 2);
                        y = genIndex * (boxHeight + spacingY);
                        
                        if (direction === 'BT') {
                            y = (genKeys.length - 1 - genIndex) * (boxHeight + spacingY);
                        }
                    } else {
                        x = genIndex * (boxWidth + spacingX);
                        var genHeight = count * boxHeight + (count - 1) * spacingY;
                        y = (i * (boxHeight + spacingY)) - (genHeight / 2) + (boxHeight / 2);
                        
                        if (direction === 'RL') {
                            x = (genKeys.length - 1 - genIndex) * (boxWidth + spacingX);
                        }
                    }
                    
                    positions[id] = { x: x, y: y };
                    maxWidth = Math.max(maxWidth, x + boxWidth);
                    maxHeight = Math.max(maxHeight, y + boxHeight);
                });
            });
            
            // Center positions
            var padding = 50;
            Object.keys(positions).forEach(function(id) {
                positions[id].x += padding + (isVertical ? maxWidth / 2 : 0);
                positions[id].y += padding + (!isVertical ? maxHeight / 2 : 0);
            });
            
            return {
                positions: positions,
                width: maxWidth + padding * 2,
                height: maxHeight + padding * 2,
                boxWidth: boxWidth,
                boxHeight: boxHeight
            };
        },
        
        createSVG: function(layout, chartData) {
            var nodes = chartData.nodes;
            var edges = chartData.edges;
            var positions = layout.positions;
            
            var width = layout.width;
            var height = layout.height;
            var boxWidth = layout.boxWidth;
            var boxHeight = layout.boxHeight;
            
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">';
            
            // Styles
            svg += '<defs><style>';
            svg += '.ftc-node rect { stroke: #333; stroke-width: 1; cursor: pointer; }';
            svg += '.ftc-node text { font-family: Arial, sans-serif; pointer-events: none; }';
            svg += '.ftc-edge { stroke: #999; stroke-width: 2; fill: none; }';
            svg += '.ftc-edge-spouse { stroke-dasharray: 5,5; }';
            svg += '</style></defs>';
            
            // Draw edges
            edges.forEach(function(edge) {
                if (positions[edge.from] && positions[edge.to]) {
                    var from = positions[edge.from];
                    var to = positions[edge.to];
                    var edgeClass = 'ftc-edge' + (edge.type === 'spouse' ? ' ftc-edge-spouse' : '');
                    
                    var x1 = from.x + boxWidth / 2;
                    var y1 = from.y + boxHeight;
                    var x2 = to.x + boxWidth / 2;
                    var y2 = to.y;
                    
                    var midY = (y1 + y2) / 2;
                    svg += '<path class="' + edgeClass + '" d="M' + x1 + ',' + y1 + ' C' + x1 + ',' + midY + ' ' + x2 + ',' + midY + ' ' + x2 + ',' + y2 + '" />';
                }
            });
            
            // Draw nodes
            Object.keys(nodes).forEach(function(id) {
                var node = nodes[id];
                var pos = positions[id];
                if (!pos) return;
                
                var x = pos.x;
                var y = pos.y;
                
                var genderColors = {
                    male: '#a8d5e5',
                    female: '#f5c6d6',
                    other: '#d4c4e8',
                    unknown: '#e0e0e0'
                };
                var fillColor = genderColors[node.gender] || genderColors.unknown;
                
                svg += '<g class="ftc-node" data-id="' + id + '">';
                svg += '<rect x="' + x + '" y="' + y + '" width="' + boxWidth + '" height="' + boxHeight + '" fill="' + fillColor + '" rx="5" ry="5" />';
                
                // Photo
                var textX = x + 10;
                if (node.photo) {
                    var photoSize = 50;
                    var photoX = x + 8;
                    var photoY = y + (boxHeight - photoSize) / 2;
                    svg += '<clipPath id="clip-' + id + '"><circle cx="' + (photoX + photoSize/2) + '" cy="' + (photoY + photoSize/2) + '" r="' + (photoSize/2) + '" /></clipPath>';
                    svg += '<image href="' + node.photo + '" x="' + photoX + '" y="' + photoY + '" width="' + photoSize + '" height="' + photoSize + '" clip-path="url(#clip-' + id + ')" preserveAspectRatio="xMidYMid slice" />';
                    textX = x + 65;
                }
                
                // Name
                var textY = y + 25;
                var maxTextWidth = boxWidth - (node.photo ? 70 : 20);
                svg += '<text x="' + textX + '" y="' + textY + '" font-size="13" font-weight="bold">';
                svg += FTC.chart.truncateText(node.name, maxTextWidth, 13);
                svg += '</text>';
                
                // Dates
                if (node.birth_date || node.death_date) {
                    var dates = '';
                    if (node.birth_date) dates += 'b. ' + node.birth_date;
                    if (node.death_date) dates += (dates ? ' ' : '') + 'd. ' + node.death_date;
                    
                    svg += '<text x="' + textX + '" y="' + (textY + 18) + '" font-size="10" fill="#666">';
                    svg += FTC.chart.truncateText(dates, maxTextWidth, 10);
                    svg += '</text>';
                }
                
                svg += '</g>';
            });
            
            svg += '</svg>';
            return svg;
        },
        
        truncateText: function(text, maxWidth, fontSize) {
            var avgCharWidth = fontSize * 0.6;
            var maxChars = Math.floor(maxWidth / avgCharWidth);
            
            if (text.length > maxChars) {
                return text.substring(0, maxChars - 2) + '...';
            }
            return text;
        },
        
        zoom: function($chart, factor) {
            var currentScale = $chart.data('scale') || 1;
            var newScale = currentScale * factor;
            newScale = Math.max(0.5, Math.min(2, newScale));
            
            $chart.data('scale', newScale);
            $chart.find('svg').css('transform', 'scale(' + newScale + ')');
            $chart.find('svg').css('transform-origin', 'center center');
        },
        
        fitToScreen: function($chart) {
            $chart.data('scale', 1);
            $chart.find('svg').css('transform', 'scale(1)');
        },
        
        enablePan: function($chart) {
            var $container = $chart.find('.ftc-chart-container');
            var isDragging = false;
            var startX, startY, scrollLeft, scrollTop;
            
            $container.on('mousedown', function(e) {
                if (e.target.closest('.ftc-node')) return;
                isDragging = true;
                $chart.addClass('zoomed');
                startX = e.pageX - $container.offset().left;
                startY = e.pageY - $container.offset().top;
                scrollLeft = $container.scrollLeft();
                scrollTop = $container.scrollTop();
            });
            
            $(document).on('mousemove', function(e) {
                if (!isDragging) return;
                e.preventDefault();
                var x = e.pageX - $container.offset().left;
                var y = e.pageY - $container.offset().top;
                var walkX = (x - startX) * 1.5;
                var walkY = (y - startY) * 1.5;
                $container.scrollLeft(scrollLeft - walkX);
                $container.scrollTop(scrollTop - walkY);
            });
            
            $(document).on('mouseup', function() {
                isDragging = false;
                $chart.removeClass('zoomed');
            });
        },
        
        exportPDF: function($chart) {
            var svg = $chart.find('svg')[0];
            if (!svg) return;
            
            // Use browser print or a library like jsPDF
            var svgData = new XMLSerializer().serializeToString(svg);
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var img = new Image();
            
            img.onload = function() {
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
                
                var link = document.createElement('a');
                link.download = 'family-tree.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            };
            
            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
        }
    };

    $(document).ready(function() {
        FTC.chart.init();
    });

})(jQuery);
