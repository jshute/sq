<?php

/**
 * Form element helpers
 *
 * Simplifies the printing of generic form elements especially the more complex
 * ones with default values.
 */

abstract class sqForm extends component {
	
	private static function parseAttrs($attrs) {
		$string = '';;
		
		if (is_array($attrs)) {
			foreach ($attrs as $key => $val) {
				if (is_int($key)) {
					$string .= ' '.$val;
				} else {
					$string .= ' '.$key.'="'.$val.'"';
				}
			}
		} else {
			$string .= ' id="'.$attrs.'"';
		}
		
		return $string;
	}
	
	// Prints form label
	public static function label($for, $value, $class = 'text') {
		$for = self::toId($for);
		
		return '<label class="'.$class.'" for="'.$for.'">'.$value.'</label>';
	}
	
	// Basic text input
	public static function text($name, $value = null, $attrs = array()) {
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
		
		return '<input type="text" name="'.$name.'" value="'.$value.'"'.$append.'/>';
	}
	
	public static function currency($name, $value = null, $attrs = array()) {
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
		
		if (!isset($attrs['class'])) {
			$append .= ' class="currency"';
		}
		
		return '&#36; <input type="text" name="'.$name.'" value="'.$value.'"'.$append.'/>';
	}
	
	// Textarea
	public static function textarea($name, $value = null, $attrs = array()) {
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
		
		return '<textarea type="text" name="'.$name.'"'.$append.'>'.htmlentities($value).'</textarea>';
	}
	
	// Hidden filed
	public static function hidden($name, $value = null, $attrs = array()) {
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
		
		return '<input type="hidden" name="'.$name.'" value="'.$value.'"'.$append.'/>';
	}
	
	// Prints file upload button. If an image is set as value it is snown beside
	// the upload button.
	public static function file($name, $value = null, $id = false, $class = null) {
		if (!$id) {
			$id = self::toId($name);
		}
		
		$content = '<div class="field-block">';
		
		if ($value) {
			$content .= '
				<img class="file-image" src="'.sq::base().$value.'"/>
				<label class="replace-image" for="'.$id.'">Replace image: </label>
			';
		}
		
		$content .= '
				<input id="'.$id.'" type="file" name="file" value=""/>
			</div>
		';
		
		return $content;
	}
	
	// Desplays a related model inline as a form within the form
	public static function inline($name, $value, $model) {
		$model = sq::model($model);
		
		if ($value) {
			$model->where($value);
			$model->read();
		} else {
			$model->limit();
			$model->schema();
		}
		
		// Inline view slightly alters the way the model form parameters work
		$model->options['inline-view'] = true;
		
		return $model;
	}
	
	// Choose from a list of related entries
	public static function single($name, $value, $model, $id = false, $class = null) {
		$model = sq::model($model);
		$model->options['load-relations'] = false;
		$model->read(array('name', 'id'));
		
		$items = array('' => '');
		
		foreach ($model as $item) {
			$items[$item->id] = $item->name;
		}
		
		return self::select($name, $value, $items, $id, $class);
	}
	
	// Prints a checkbox. Optionally checked
	public static function checkbox($name, $checked = null, $attrs = array()) {		
		$zeroFill = true;
		
		if (is_array($attrs)) {
			if (isset($attrs['zeroFill'])) {
				$zeroFill = $attrs['zeroFill'];
				unset($attrs['zeroFill']);
			}
		}
		
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
			
		if ($checked) {
			$checked = 'checked';
		}
		
		$content = '';
		
		if ($zeroFill) {
			$content .= '<input type="hidden" name="'.$name.'" value="0"/>';
		}
		
		$content .= '<input type="checkbox" name="'.$name.'" value="1" '.$checked.$append.'/>';
	
		return $content;
	}
	
	// Prints a select box with an array of data
	public static function select($name, $default, $data, $attrs = array()) {
		$append = self::parseAttrs($attrs);
		
		if (!isset($attrs['id'])) {
			$append .= ' id="'.self::toId($name).'"';
		}
		
		if (is_string($data)) {
			$data = sq::config($data);
		}
		
		$content = '<select name="'.$name.'"'.$append.'>';
		foreach ($data as $value => $label) {
			
			$selected = null;
			if ($default == $value && $default !== false) {
				$selected = 'selected';
			}
			
			$content .= '<option '.$selected.' value="'.$value.'">'.$label.'</option>';
		}
		
		$content .= '</select>';
		
		return $content;
	}
	
	// Similar to textarea but with a richtext class presumably to use tinyMCE or
	// suchlike
	public static function richtext($name, $content = null, $id = false, $class = null) {
		if (!$id) {
			$id = self::toId($name);
		}
		
		return '<textarea name="'.$name.'" class="richtext '.$class.'" id="'.$id.'">'.htmlentities($content).'</textarea>';
	}
	
	// Textarea
	public static function blurb($name, $content = null, $id = false, $class = null) {
		if (!$id) {
			$id = self::toId($name);
		}
		
		return '<textarea name="'.$name.'" class="blurb '.$class.'" id="'.$id.'">'.htmlentities($content).'</textarea>';
	}
	
	// Utility method to take a name parameter and convert it to a standard dashed
	// id name
	private static function toId($string) {
		$string = preg_replace('/[^0-9a-zA-Z -]/', '', $string);
		$string = preg_replace('!\s+!', ' ', $string);
		$string = str_replace(' ', '-', $string);
		$string = strtolower($string);

		return $string;
	}
}

?>