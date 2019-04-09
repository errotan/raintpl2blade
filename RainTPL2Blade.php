<?php

/*
 * Copyright (c) 2011-2017 Federico Ulfo <rainelemental@gmail.com>
 * Copyright (c) 2019 Puskás Zsolt <errotan@gmail.com>
 * Licensed under the MIT license.
 */

class RainTPL2Blade
{
    /**
     * You can define in the black list what string are disabled into the template tags
     *
     * @var array
     */
    public $black_list = array( '\$this', 'raintpl::', 'self::', '_SESSION', '_SERVER', '_ENV',  'eval', 'exec', 'unlink', 'rmdir' );

    /**
     * PHP tags <? ?>
     * True: php tags are enabled into the template
     * False: php tags are disabled into the template and rendered as html
     *
     * @var bool
     */
    public $php_enabled = false;

    /**
     * @var string
     */
    protected $template;

    /**
     * @param string $template
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    public function convert()
    {
        //xml substitution
		$template_code = preg_replace( "/<\?xml(.*?)\?>/s", "##XML\\1XML##", $this->template);

		//disable php tag
		if( !$this->php_enabled )
			$template_code = str_replace( array("<?","?>"), array("&lt;?","?&gt;"), $template_code );

		//xml re-substitution
		$template_code = preg_replace_callback ( "/##XML(.*?)XML##/s", array($this, 'xml_reSubstitution'), $template_code ); 

		//compile template
		$template_compiled = $this->compileTemplate( $template_code );

		// fix the php-eating-newline-after-closing-tag-problem
		return str_replace( "?>\n", "?>\n\n", $template_compiled );
	}

    /**
     * execute stripslaches() on the xml block. Invoqued by preg_replace_callback function below
     * @access protected
     */
    protected function xml_reSubstitution($capture) {
        return "<?xml ".stripslashes($capture[1])." ?>";
    }

    /**
	 * Compile template
	 * @access protected
	 */
	protected function compileTemplate( $template_code ){

		//tag list
		$tag_regexp = array( 'loop'         => '(\{loop(?: name){0,1}="\${0,1}[^"]*"\})',
                             'break'	    => '(\{break\})',
                             'continue'	    => '(\{continue\})',
                             'loop_close'   => '(\{\/loop\})',
                             'if'           => '(\{if(?: condition){0,1}="[^"]*"\})',
                             'elseif'       => '(\{elseif(?: condition){0,1}="[^"]*"\})',
                             'else'         => '(\{else\})',
                             'if_close'     => '(\{\/if\})',
                             'function'     => '(\{function="[^"]*"\})',
                             'noparse'      => '(\{noparse\})',
                             'noparse_close'=> '(\{\/noparse\})',
                             'ignore'       => '(\{ignore\}|\{\*)',
                             'ignore_close'	=> '(\{\/ignore\}|\*\})',
                             'include'      => '(\{include="[^"]*"(?: cache="[^"]*")?\})',
                             'template_info'=> '(\{\$template_info\})',
                             'function'		=> '(\{function="(\w*?)(?:.*?)"\})'
							);

		$tag_regexp = "/" . join( "|", $tag_regexp ) . "/";

		//split the code with the tags regexp
		$template_code = preg_split ( $tag_regexp, $template_code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		//return the compiled code
		return $this->compileCode( $template_code );
	}

	/**
	 * Compile the code
	 */
	protected function compileCode( $parsed_code ){
            
                // if parsed code is empty return null string
                if( !$parsed_code )
                    return "";

		//variables initialization
		$compiled_code = $open_if = $comment_is_open = $ignore_is_open = null;
                $loop_level = 0;

                
	 	//read all parsed code
	 	foreach( $parsed_code as $html ){

	 		//close ignore tag
			if( !$comment_is_open && ( strpos( $html, '{/ignore}' ) !== FALSE || strpos( $html, '*}' ) !== FALSE ) ) {
                $compiled_code .=   ' --}}';
	 			$ignore_is_open = false;
            }

	 		//code between tag ignore id deleted
	 		elseif( $ignore_is_open ){
                //ignored code
			    $compiled_code .= $html;
	 		}

	 		//close no parse tag
			elseif( strpos( $html, '{/noparse}' ) !== FALSE )
	 			$comment_is_open = false;

	 		//code between tag noparse is not compiled
	 		elseif( $comment_is_open )
 				$compiled_code .= $html;

	 		//ignore
			elseif( strpos( $html, '{ignore}' ) !== FALSE || strpos( $html, '{*' ) !== FALSE ) {
                $compiled_code .=   '{{-- ';
                $ignore_is_open = true;
            }

	 		//noparse
	 		elseif( strpos( $html, '{noparse}' ) !== FALSE )
	 			$comment_is_open = true;

			//include tag
			elseif( preg_match( '/\{include="([^"]*)"(?: cache="([^"]*)"){0,1}\}/', $html, $code ) ){
				if (0 === strpos($code[1], 'http')) {
					throw new RainTPL_SyntaxException(
					    sprintf(
					        'Not allowed syntax in template on line #%s (%s)',
                            $this->findLine($code[1]),
                            $code[1]
                        )
                    );
				}

                //variables substitution
                $include_var = $this->var_replace( $code[ 1 ], $left_delimiter = null, $right_delimiter = null, $php_left_delimiter = '".' , $php_right_delimiter = '."', $loop_level );

                // reduce the path
                $include_template = $this->reduce_path( $include_var );

                $compiled_code .= ' @include(\''.$include_template.'\') ';
			}

	 		//loop
			elseif( preg_match( '/\{loop(?: name){0,1}="\${0,1}([^"]*)"\}/', $html, $code ) ){

	 			//increase the loop counter
	 			$loop_level++;

				//replace the variable in the loop
				$var = $this->var_replace( '$' . $code[ 1 ], $tag_left_delimiter=null, $tag_right_delimiter=null, $php_left_delimiter=null, $php_right_delimiter=null, $loop_level-1 );

				//loop variables
				$counter = "\$counter$loop_level";       // count iteration
				$key = "\$key$loop_level";               // key
				$value = "\$value$loop_level";           // value

				//loop code
				$compiled_code .=  " {{ $counter = -1 }} @if (isset($var) && is_array($var)) @foreach ($var as $key => $value) {{ $counter++ }}";

			}

            // loop break
            elseif( strpos( $html, '{break}' ) !== FALSE ) {

                $compiled_code .=   ' @break ';

            }

            // loop continue
            elseif( strpos( $html, '{continue}' ) !== FALSE ) {

                $compiled_code .=   ' @continue ';

            }

            //close loop tag
			elseif( strpos( $html, '{/loop}' ) !== FALSE ) {

				//iterator
				$counter = "\$counter$loop_level";

				//decrease the loop counter
				$loop_level--;

				//close loop code
				$compiled_code .=  " @endforeach @endif ";

			}

			//if
			elseif( preg_match( '/\{if(?: condition){0,1}="([^"]*)"\}/', $html, $code ) ){

				//increase open if counter (for intendation)
				$open_if++;

				//tag
				$tag = $code[ 0 ];

				//condition attribute
				$condition = $code[ 1 ];

				// check if there's any function disabled by black_list
				$this->function_check( $tag );

				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level );

				//if code
				$compiled_code .=   " @if ($parsed_condition) ";

			}

			//elseif
			elseif( preg_match( '/\{elseif(?: condition){0,1}="([^"]*)"\}/', $html, $code ) ){

				//tag
				$tag = $code[ 0 ];

				//condition attribute
				$condition = $code[ 1 ];

				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level );

				//elseif code
				$compiled_code .=   " @elseif ($parsed_condition) ";
			}

			//else
			elseif( strpos( $html, '{else}' ) !== FALSE ) {

				//else code
				$compiled_code .=   ' @else ';

			}

			//close if tag
			elseif( strpos( $html, '{/if}' ) !== FALSE ) {

				//decrease if counter
				$open_if--;

				// close if code
				$compiled_code .=   ' @endif ';

			}

			//function
			elseif( preg_match( '/\{function="(\w*)(.*?)"\}/', $html, $code ) ){

				//tag
				$tag = $code[ 0 ];

				//function
				$function = $code[ 1 ];

				// check if there's any function disabled by black_list
				$this->function_check( $tag );

				if( empty( $code[ 2 ] ) )
					$parsed_function = $function . "()";
				else
					// parse the function
					$parsed_function = $function . $this->var_replace( $code[ 2 ], $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level );
				
				//if code
				$compiled_code .=   '{{ '.$parsed_function.' }}';
			}
			//all html code
			else{

				//variables substitution (es. {$title})
				$html = $this->var_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '{{ ', $php_right_delimiter = ' }}', $loop_level, $echo = true );
				//const substitution (es. {#CONST#})
				$html = $this->const_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '{{ ', $php_right_delimiter = ' }}', $loop_level, $echo = true );
				//functions substitution (es. {"string"|functions})
				$compiled_code .= $this->func_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '{{ ', $php_right_delimiter = ' }}', $loop_level, $echo = true );
			}
		}

		if( $open_if > 0 ) {
			throw new RainTPL_SyntaxException('Error! You need to close an {if} tag in template');
		}
		return $compiled_code;
	}

	/**
	 * Reduce a path, eg. www/library/../filepath//file => www/filepath/file
	 * @param type $path
	 * @return type
	 */
	protected function reduce_path( $path ){
            $path = str_replace( "://", "@not_replace@", $path );
            $path = preg_replace( "#(/+)#", "/", $path );
            $path = preg_replace( "#(/\./+)#", "/", $path );
            $path = str_replace( "@not_replace@", "://", $path );
            
            while( preg_match( '#\.\./#', $path ) ){
                $path = preg_replace('#\w+/\.\./#', '', $path );
            }
            return $path;
	}

	// replace const
	function const_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){
		// const
		return preg_replace( '/\{\#(\w+)\#{0,1}\}/', $php_left_delimiter . ( $echo ? '' : null ) . '\\1' . $php_right_delimiter, $html );
	}



	// replace functions/modifiers on constants and strings
	function func_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){

		preg_match_all( '/' . '\{\#{0,1}(\"{0,1}.*?\"{0,1})(\|\w.*?)\#{0,1}\}' . '/', $html, $matches );

		for( $i=0, $n=count($matches[0]); $i<$n; $i++ ){

			//complete tag ex: {$news.title|substr:0,100}
			$tag = $matches[ 0 ][ $i ];

			//variable name ex: news.title
			$var = $matches[ 1 ][ $i ];

			//function and parameters associate to the variable ex: substr:0,100
			$extra_var = $matches[ 2 ][ $i ];

			// check if there's any function disabled by black_list
			$this->function_check( $tag );

			$extra_var = $this->var_replace( $extra_var, null, null, null, null, $loop_level );


			// check if there's an operator = in the variable tags, if there's this is an initialization so it will not output any value
			$is_init_variable = preg_match( "/^(\s*?)\=[^=](.*?)$/", $extra_var );

			//function associate to variable
			$function_var = ( $extra_var and $extra_var[0] == '|') ? substr( $extra_var, 1 ) : null;

			//variable path split array (ex. $news.title o $news[title]) or object (ex. $news->title)
			$temp = preg_split( "/\.|\[|\-\>/", $var );

			//variable name
			$var_name = $temp[ 0 ];

			//variable path
			$variable_path = substr( $var, strlen( $var_name ) );

			//parentesis transform [ e ] in [" e in "]
			$variable_path = str_replace( '[', '["', $variable_path );
			$variable_path = str_replace( ']', '"]', $variable_path );

			//transform .$variable in ["$variable"]
			$variable_path = preg_replace('/\.\$(\w+)/', '["$\\1"]', $variable_path );

			//transform [variable] in ["variable"]
			$variable_path = preg_replace('/\.(\w+)/', '["\\1"]', $variable_path );

			//if there's a function
			if( $function_var ){
                
                // check if there's a function or a static method and separate, function by parameters
				$function_var = str_replace("::", "@double_dot@", $function_var );

                // get the position of the first :
                if( $dot_position = strpos( $function_var, ":" ) ){

                    // get the function and the parameters
                    $function = substr( $function_var, 0, $dot_position );
                    $params = substr( $function_var, $dot_position+1 );

                }
                else{

                    //get the function
                    $function = str_replace( "@double_dot@", "::", $function_var );
                    $params = null;

                }

                // replace back the @double_dot@ with ::
                $function = str_replace( "@double_dot@", "::", $function );
                $params = str_replace( "@double_dot@", "::", $params );


			}
			else
				$function = $params = null;

			$php_var = $var_name . $variable_path;

			// compile the variable for php
			if( isset( $function ) ){
				if( $php_var )
					$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? '' : null ) . ( $params ? "$function($php_var, $params)" : "$function($php_var)" ) . $php_right_delimiter;
				else
					$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? '' : null ) . ( $params ? "$function($params)" : "$function()" ) . $php_right_delimiter;
			}
			else
				$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? '' : null ) . $php_var . $extra_var . $php_right_delimiter;

			$html = str_replace( $tag, $php_var, $html );

		}

		return $html;
	}



	function var_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){

		//all variables
		if( preg_match_all( '/' . $tag_left_delimiter . '\$(\w+(?:\.\${0,1}[A-Za-z0-9_]+)*(?:(?:\[\${0,1}[A-Za-z0-9_]+\])|(?:\-\>\${0,1}[A-Za-z0-9_]+))*)(.*?)' . $tag_right_delimiter . '/', $html, $matches ) ){

                    for( $parsed=array(), $i=0, $n=count($matches[0]); $i<$n; $i++ )
                        $parsed[$matches[0][$i]] = array('var'=>$matches[1][$i],'extra_var'=>$matches[2][$i]);

                    foreach( $parsed as $tag => $array ){

                            //variable name ex: news.title
                            $var = $array['var'];

                            //function and parameters associate to the variable ex: substr:0,100
                            $extra_var = $array['extra_var'];

                            // check if there's any function disabled by black_list
                            $this->function_check( $tag );

                            $extra_var = $this->var_replace( $extra_var, null, null, null, null, $loop_level );

                            // check if there's an operator = in the variable tags, if there's this is an initialization so it will not output any value
                            $is_init_variable = preg_match( "/^[a-z_A-Z\.\[\](\-\>)]*=[^=]*$/", $extra_var );
                            
                            //function associate to variable
                            $function_var = ( $extra_var and $extra_var[0] == '|') ? substr( $extra_var, 1 ) : null;

                            //variable path split array (ex. $news.title o $news[title]) or object (ex. $news->title)
                            $temp = preg_split( "/\.|\[|\-\>/", $var );

                            //variable name
                            $var_name = $temp[ 0 ];

                            //variable path
                            $variable_path = substr( $var, strlen( $var_name ) );

                            //parentesis transform [ e ] in [" e in "]
                            $variable_path = str_replace( '[', '["', $variable_path );
                            $variable_path = str_replace( ']', '"]', $variable_path );

                            //transform .$variable in ["$variable"] and .variable in ["variable"]
                            $variable_path = preg_replace('/\.(\${0,1}\w+)/', '["\\1"]', $variable_path );
                            
                            // if is an assignment also assign the variable to $this->var['value']
                            /* if( $is_init_variable )
                                $extra_var = "=\$this->var['{$var_name}']{$variable_path}" . $extra_var; */

                                

                            //if there's a function
                            if( $function_var ){
                                
                                    // check if there's a function or a static method and separate, function by parameters
                                    $function_var = str_replace("::", "@double_dot@", $function_var );


                                    // get the position of the first :
                                    if( $dot_position = strpos( $function_var, ":" ) ){

                                        // get the function and the parameters
                                        $function = substr( $function_var, 0, $dot_position );
                                        $params = substr( $function_var, $dot_position+1 );

                                    }
                                    else{

                                        //get the function
                                        $function = str_replace( "@double_dot@", "::", $function_var );
                                        $params = null;

                                    }

                                    // replace back the @double_dot@ with ::
                                    $function = str_replace( "@double_dot@", "::", $function );
                                    $params = str_replace( "@double_dot@", "::", $params );
                            }
                            else
                                    $function = $params = null;

                            //if it is inside a loop
                            if( $loop_level ){
                                    //verify the variable name
                                    if( $var_name == 'key' )
                                            $php_var = '$key' . $loop_level;
                                    elseif( $var_name == 'value' )
                                            $php_var = '$value' . $loop_level . $variable_path;
                                    elseif( $var_name == 'counter' )
                                            $php_var = '$counter' . $loop_level;
                                    else
                                            $php_var = '$' . $var_name . $variable_path;
                            }else
                                    $php_var = '$' . $var_name . $variable_path;
                            
                            // compile the variable for php
                            if( isset( $function ) )
                                    $php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? '' : null ) . ( $params ? "$function($php_var, $params)" : "$function($php_var)" ) . $php_right_delimiter;
                            else
                                    $php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? '' : null ) . $php_var . $extra_var . $php_right_delimiter;
                            
                            $html = str_replace( $tag, $php_var, $html );


                    }
                }

		return $html;
	}



	/**
	 * Check if function is in black list (sandbox)
	 *
	 * @param string $code
	 * @param string $tag
     *
     * @throws RainTPL_SyntaxException
	 */
	protected function function_check( $code )
    {
		$preg = '#(\W|\s)' . implode( '(\W|\s)|(\W|\s)', $this->black_list ) . '(\W|\s)#';

		// check if the function is in the black list (or not in white list)
		if( count($this->black_list) && preg_match( $preg, $code, $match ) ){

		    $line = $this->findLine($code);

			// stop the execution of the script
			throw new RainTPL_SyntaxException(
			    sprintf('Not allowed syntax in template on line #%s (%s)', $line, $code)
            );
		}
	}

    /**
     * @param string $code
     * @return int
     */
	protected function findLine($code)
    {
        // find the line of the error
        $line = 0;
        $rows = explode("\n",$this->template);
        while( !strpos($rows[$line],$code) )
            $line++;

        return $line;
    }
}
