<?php
namespace DebugHelper;

class Styles
{
    /**
     * Show the styles given if not shown before.
     */
    public static function showHeader()
    {
        static $shown_headers = array();

        if (\DebugHelper::isCli()) {
            $keys = array();
        } else {
            $keys = func_get_args();
        }

        $headers = array();

        $headers['error'] = <<<HEAD
<style type="text/css">
div.error_handler { margin:5px; padding: 8px 35px 8px 26px; }
div.error_handler_notice {
	background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAPCAYAAADtc08vAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAN1wAADdcBQiibeAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAADgSURBVCiRndI9SgRBEIbhp0cQBCNBAwPBA4ihRqKBYKDgzxnMDIS9hok3MDA0FK+gsBfYCxiYiZEiWiYz0ELN7LIFH3RXf/VWddMlImRRSlnAAxZxGhHfqTEiUuES0eqq19dTvITXCvCG5czbpGNxjfVqv4bRTFfACt7bzhc4adcfWJ16Bdx0o1e5zzZ3OwjARmXOAF/YHALcVQ+XAQL3KQBb+JkB8IvtDPBYF7c6wkGSf/oHwF5imqb9GvDcYzrGYc/ZS0RoSiln2E0/yXDslFLOYTzH+J3GDSZzdO9i8gfS2jUnJ9HshAAAAABJRU5ErkJggg==) no-repeat #fcf8e3 8px 10px;
	border:1px solid #fbeed5;
	color:#C09853;
}
div.error_handler_warning, div.error_handler_error {
	background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAPCAYAAADtc08vAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAN1wAADdcBQiibeAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAADgSURBVCiRndI9SgRBEIbhp0cQBCNBAwPBA4ihRqKBYKDgzxnMDIS9hok3MDA0FK+gsBfYCxiYiZEiWiYz0ELN7LIFH3RXf/VWddMlImRRSlnAAxZxGhHfqTEiUuES0eqq19dTvITXCvCG5czbpGNxjfVqv4bRTFfACt7bzhc4adcfWJ16Bdx0o1e5zzZ3OwjARmXOAF/YHALcVQ+XAQL3KQBb+JkB8IvtDPBYF7c6wkGSf/oHwF5imqb9GvDcYzrGYc/ZS0RoSiln2E0/yXDslFLOYTzH+J3GDSZzdO9i8gfS2jUnJ9HshAAAAABJRU5ErkJggg==) no-repeat #f2dede 8px 10px;
	border:1px solid #eed3d7;
	color:#b94a48;
}
</style>
HEAD;

        $headers['dump'] = <<<HEAD
<style type="text/css">
div.debug_dump { margin:1px; background:#FFFF99; overflow:hidden; font-family:arial; font-size:12px; font-weight:bold; border: 2px solid #DFDFDF; }
div.debug_dump span.timer {
	background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAECQAABAkB7+5w6gAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAC9SURBVBiVXdAtTkNREIbhhwsOGuQ1JCUEfEXTpDtAI1gADotgAchaNoChNf0z3UBNfQ0CgsOgIA0SBsFcOGGSEXPmzXsyn4jQNFro4wgVZjiPCA1QY4xPRPYD7vGFyy3sYZ22G7z6qSv0MEzGAB84SfsdOtjGKs3VDk4xjIhHf9XFfkqmOKzQxlMBLXCcgk2+1bDEvLy+SOEiD2w1Q+DsH3SAF4zLeEYZwwTXuMUbnlH/gsU3S7xnXAPsNvtv2ONnIg5OeVsAAAAASUVORK5CYII=) no-repeat 2px 2px;
	padding-left: 14px;
}
div.debug_dump div.header { background:#DFDFDF; height:auto; }
div.debug_dump div.header span.code { margin-left:100px; }
div.debug_dump div.data { padding:5px; }
</style>
HEAD;

        $headers['objectToHtml'] = <<<HEAD
<style type="text/css">
	ul.object_dump {
		margin:0px;
		list-style:none;
		padding:0px;
		font-family:arial;
		font-size: 12px;
	}
	ul.object_dump ul {
		margin:0px;
		list-style:none;
		border:1px solid #DDDDDD;
		background:#EEEEEE;
	}
	ul.object_dump li {
		padding:2px;
		width:100%;
		overflow:hidden;
	}
	ul.object_dump li span.row {
		background-color: #DDDDDD;
		font-weight:bold;
		width: 100%;
		padding:2px 20px;
		display:block;
	}
	ul.object_dump li.collapsed > span.row {
		cursor:pointer;
		background: url( data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABjSURBVCjPY/jPgB8y0FHBkb37/+/6v+X/+v8r/y/ei0XB3v+H4HDWfywKtgAl1v7/D8SH/k/ApmANUAICDv1vx6ZgMZIJ9dgUzEJyQxk2BRPWdf1vAeqt/F/yP3/dwIQk2QoAfUogHsamBmcAAAAASUVORK5CYII ) #DDDDDD no-repeat 3px 3px;
	}
	ul.object_dump li.expanded > span.row {
		cursor:pointer;
		background: url( data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABhSURBVCjPY/jPgB8y0FHBkb37/+/6v+X/+v8r/y/ei0XB3v+H4HDWfywKtgAl1oLhof8TsClYA5SAgEP/27EpWIxkQj02BbOQ3FCGTcGEdV3/W4B6K/+X/M9fNzAhSbYCAMiTH3pTNa+FAAAAAElFTkSuQmCC ) #DDDDDD no-repeat 3px 3px;
	}
	ul.object_dump li.collapsed { height:20px; }

	ul.object_dump span.key {
		background-color: #f89406;
		display: inline-block;
		color: #ffffff;
		margin-right: 5px;
		white-space: nowrap;
		vertical-align: baseline;
		padding: 3px 5px;
		font-style:normal;
		font-weight:bold;
	}
	ul.object_dump span.type { margin-right: 5px; }
	ul.object_dump span.string { color:#008800; }
	ul.object_dump span.integer { color:#880000; }
	ul.object_dump span.boolean { color:#000088; }

	</style>
	<script type="text/javascript">
	function toggleObjectToHtmlNode(id) {
		node = document.getElementById('debug_node_' + id);
		node.className = node.className == 'collapsed' ? 'expanded' : 'collapsed';
		return false;
	}
	</script>
HEAD;

        $headers['getCallers'] = <<<HEADER
<style type="text/css">
a.debug_caller {
	font-family:monospace;
	color:black;
	text-decoration:none;
	text-decoration:none !important;
}
a.debug_caller span.line {background:#CCC; margin-right:5px; float:right;}
a.debug_caller span.method {background:rgba(72,237,67,0.20); margin-right:5px; font-weight:bold; float:right;}
span.dump:hover a.debug_caller { display:inline; }
</style>

HEADER;

        $headers['showtrace'] = <<<HEAD
	<style type="text/css">
		#showtrace { border-collapse: collapse; }
		#showtrace tr.showtrace_row { background-color:#DDD; font-family:arial; font-weight:bold; font-size:12px; border:2px solid #EEEEEE; }
		#showtrace tr.showtrace_row div.params { position:absolute; display:none; }
		#showtrace tr.showtrace_row:hover div.params { display:block; }
		#showtrace td { padding:3px; }
			#showtrace td.position a { color:black; text-decoration:none; font-family:monospace; }
		#showtrace .even { background-color:#DEDEDE; }
		#showtrace .odd { background-color:#FFFFFF; }
	</style>
	<script type="text/javascript">
	function dirty_toggleNode(id) {
		node = document.getElementById('debug_node_' + id);
		node.style.display = node.style.display == 'none' ? 'block' : 'none';
		return false;
	}
	</script>
HEAD;

        $headers['to_html'] = <<<HEAD
	<style type="text/css">
		.debug_output { display:inline; }
		.debug_output ul { border-left: 1px solid red; padding: 0 0 0 20px; margin:0; list-style:none; }
		.debug_output ul:hover { border-left: 1px solid black; }
		.debug_output pre { margin: 0px; }
	</style>
	<script type="text/javascript">
	function toggleNode(id) {
		node = document.getElementById('debug_node_' + id);
		node.style.display = node.style.display == 'none' ? 'block' : 'none';
		return false;
	}
	</script>

HEAD;

        $headers['profileReport'] = <<<HEADER
<style>
	pre.profile_report > div { }
	pre.profile_report div.label { width:100%; color:black; font-size:12px; }
	pre.profile_report div.rate { background-color: red; height:3px; }
		pre.profile_report div.label span { float:right; }
</style>
HEADER;

        $headers['export'] = <<<HEADER
<style>
	pre.debug_export { background:#FFFF99;}
</style>
HEADER;

        foreach ($keys as $key) {
            if (!empty($shown_headers[$key])) {
                return false;
            }
            $shown_headers[$key] = true;

            $header = preg_replace('/\s+/', ' ', $headers[$key]);
            echo $header . "\n";
        }
    }
}