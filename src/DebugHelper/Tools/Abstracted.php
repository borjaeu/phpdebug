<?php
namespace DebugHelper\Tools;

class Abstracted
{
	/**
	 * Exports the filter debug info.
	 *
	 * @param integer $depth Depth of the callers to get.
	 * @param boolean $show_header
	 * @return string
	 */
	protected function getCallerHtml( $depth, $show_header = true )
	{
		$item = $this->getCallerInfo( false, $depth + 1 );
		if ( false === $item )
		{
			return false;
		}

		$title = $item['function'];
		if ( isset( $item['class'] ) && isset( $item['type'] ) )
		{
			$title = $item['class'] . $item['type'] . $title;
		}

		$id = uniqid();

		if ( $show_header )
		{
			\DebugHelper\Styles::showHeader( 'getCallers' );
		}

		if ( isset( $item['file'] ) )
		{
			// Get code line.
			$code = self::getCodeLineInfo( $item['file'], $item['line'] );
			$line = trim( str_replace( "\t", '→|', $code['source'] ) );
			$file = basename( $item['file'] );

			$link = <<<POS
<a class="debug_caller" name="$id" href="codebrowser:{$item['file']}:{$item['line']}" title="in {$code['class']}::{$code['method']}()">$line<span class="line">$file:{$item['line']}</span></a>
POS;
		}
		else
		{
			$link = <<<POS
$title
POS;
		}
		return $link;
	}

	/**
	 * Exports the filter debug info.
	 *
	 * @param integer $depth Depth of the callers to get.
	 * @return string
	 */
	protected function getCallerSource( $depth )
	{
		$item = $this->getCallerInfo( false, $depth + 1 );
		if ( isset( $item['file'] ) )
		{
			// Get code line.
			$code = self::getCodeLineInfo( $item['file'], $item['line'] );
			return $code['source'];
		}
		return '';
	}

	/**
	 * Returns the information of the function dirty_that called this one.
	 *
	 * @param boolean $key Return only one of the keys of the array.
	 * @param integer $depth Numbers of method to go back.
	 * @return array
	 */
	protected function getCallerInfo( $key = false, $depth = 2 )
	{
		$trace = debug_backtrace( false );

		if ( !isset( $trace[$depth] ) )
		{
			return false;
		}
		$item = $trace[$depth];
		if ( $key )
		{
			return $item[$key];
		}
		return $item;
	}


	/**
	 * Gets information about a code file by opening the file and reading the PHP code.
	 *
	 * @param string $file Path to the file
	 * @param integer $line Line number
	 * @return array
	 */
	protected function getCodeLineInfo( $file, $line )
	{
		$result = array(
			'class'		=> false,
			'method'	=> false,
			'source'	=> ''
		);

		if ( !is_file( $file ) )
		{
			return $result;
		}

		// Get code line.
		$fp = fopen( $file, 'r' );
		$line_no = 0;
		while ( $line_no++ < $line )
		{
			$result['source'] = fgets( $fp );
			if ( preg_match( '/^\s*(abstract)?\s*[cC]lass\s+([^\s]*)\s*(extends)?\s*([^\s]*)/', $result['source'], $matches ) )
			{
				$result['class'] = $matches[2];
			}
			else if ( preg_match( '/^\s+(.*)function\s+([^\(]*)\((.*)\)/', $result['source'], $matches ) )
			{
				$result['method'] = $matches[2];
			}
		}
		return $result;
	}

	/**
	 * Convert data to HTML.
	 *
	 * @param object $data Data to convert to array
	 * @return string
	*/
	protected function objectToHtml( $data, $key = false, $level = 0 )
	{
		static $id = 0;

		$debug = $level == 0 ? sprintf( "<ul class=\"object_dump\">" ) : '';

		$extra = '';
		$type = 'array';
		$value = 'array';
		if ( is_object( $data ) )
		{
			$value = get_class( $data );
			$data = get_object_vars( $data );
			$type = 'object(' . count( $data ) . ')';
		}
		if ( is_array( $data ) )
		{
			$type .= '(' . count( $data ) . ')';
			$extra = '<ul>';
			foreach ( $data as $sub_key => $sub_value )
			{
				$extra .= $this->objectToHtml( $sub_value, $sub_key, $level + 1 );
			}
			$extra .= '</ul>';
		}
		else
		{
			if ( is_string( $data ) )
			{
				$size = strlen( $data );
				$type = 'string(' . $size . ')';
				if ( $size > 160 )
				{
					$extra = '<pre>' . htmlentities( $data ) . '</pre>';
					$data = substr( $data, 0, 160 ) . '[...]';
				}
				$value = htmlentities( $data );
			}
			elseif ( is_null( $data ) )
			{
				$type = 'null';
				$value = '';
			}
			elseif ( is_integer( $data ) )
			{
				$type = 'integer';
				$value = $data;
			}
			elseif ( is_bool( $data ) )
			{
				$type = 'boolean';
				$value = $data ? 'true' : 'false';
			}
			elseif ( is_float( $data ) )
			{
				$type = 'float';
				$value = $data;
			}
			else
			{
				echo 'Unknown type';
				var_dump( $data );
				die(sprintf("<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__));
			}
		}

		$id++;

		preg_match( '/^\w+/', $type, $type_class );

		if ( false !== $key )
		{
			$key = '<span class="key">' . $key . '</span>';
		}
		$status = \DebugHelper::isEnabled( \DebugHelper::DUMP_COLLAPSED ) ? 'collapsed' : 'expanded';
		if ( $extra )
		{
			$debug .= <<<HTML
<li id="debug_node_$id" class="$status">
	<span class="row" onclick="return toggleObjectToHtmlNode($id);">
		$key<span class="type">$type</span><span class="{$type_class[0]}">$value</span>
	</span>$extra
</li>

HTML;
		}
		else
		{
			$debug .= <<<HTML
<li id="debug_node_$id">
	<span class="row">
		$key<span class="type">$type</span><span class="{$type_class[0]}">$value</span>
	</span>
</li>

HTML;
		}
		if ( $level == 0 )
		{
			$debug .= '</ul>';
		}

		return $debug;
	}

}