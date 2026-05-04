<?php

namespace Nilambar\Optiz;

class Assets {

	public function enqueue( string $hook, string $page_hook, array $schema ): void {
		if ( $hook !== $page_hook ) {
			return;
		}

		wp_enqueue_style(
			'optiz-admin',
			OPTIZ_URL . 'assets/css/optiz-admin.css',
			[],
			OPTIZ_LOADED_VERSION
		);

		wp_enqueue_script(
			'optiz-conditional',
			OPTIZ_URL . 'assets/js/conditional.js',
			[],
			OPTIZ_LOADED_VERSION,
			true
		);

		$rules               = [];
		$has_code_field      = false;
		$has_color_field     = false;
		$has_image_field     = false;
		$has_buttonset_field = false;

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( ! empty( $field['conditions'] ) ) {
					$rules[] = [
						'fieldId'    => $field['id'],
						'conditions' => $field['conditions'],
					];
				}

				switch ( $field['type'] ) {
					case 'code':
						$has_code_field = true;
						break;
					case 'color':
						$has_color_field = true;
						break;
					case 'image':
						$has_image_field = true;
						break;
					case 'buttonset':
						$has_buttonset_field = true;
						break;
				}
			}
		}

		wp_localize_script( 'optiz-conditional', 'optizConditional', [ 'rules' => $rules ] );

		if ( $has_code_field ) {
			$editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );

			if ( false !== $editor_settings ) {
				$mime_map = [
					'text' => 'text/plain',
					'css'  => 'text/css',
					'js'   => 'text/javascript',
				];
				wp_add_inline_script(
					'code-editor',
					'document.addEventListener("DOMContentLoaded",function(){' .
					'var s=' . wp_json_encode( $editor_settings ) . ';' .
					'var m=' . wp_json_encode( $mime_map ) . ';' .
					'document.querySelectorAll(".optiz-code-editor").forEach(function(el){' .
					'var c=Object.assign({},s,{codemirror:Object.assign({},s.codemirror,{mode:m[el.dataset.codeType]||"text/plain"})});' .
					'wp.codeEditor.initialize(el,c);' .
					'});});'
				);
			}
		}

		if ( $has_color_field ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script(
				'wp-color-picker',
				'jQuery(function($){$(".optiz-color-picker").wpColorPicker();});'
			);
		}

		if ( $has_buttonset_field ) {
			wp_add_inline_script( 'optiz-conditional', $this->buttonset_script(), 'after' );
		}

		if ( $has_image_field ) {
			wp_enqueue_media();
			wp_add_inline_script( 'optiz-conditional', $this->image_picker_script(), 'after' );
		}
	}

	private function buttonset_script(): string {
		return 'document.addEventListener("DOMContentLoaded",function(){' .
			'document.querySelectorAll(".optiz-buttonset").forEach(function(bs){' .
			'bs.addEventListener("change",function(e){' .
			'if(e.target.type!=="radio")return;' .
			'bs.querySelectorAll(".optiz-buttonset-item").forEach(function(item){' .
			'item.classList.toggle("is-active",item.querySelector("input[type=\'radio\']")===e.target);' .
			'});' .
			'});' .
			'});' .
			'});';
	}

	private function image_picker_script(): string {
		return 'document.addEventListener("DOMContentLoaded",function(){' .
			'document.querySelectorAll(".optiz-image-field").forEach(function(w){' .
			'var u=w.querySelector(".optiz-image-url");' .
			'var up=w.querySelector(".optiz-upload-image");' .
			'var rm=w.querySelector(".optiz-remove-image");' .
			'var pv=w.querySelector(".optiz-image-preview");' .
			'var pi=pv?pv.querySelector("img"):null;' .
			'if(!up||!u)return;' .
			'var fr;' .
			'up.addEventListener("click",function(e){' .
			'e.preventDefault();' .
			'if(!fr){' .
			'fr=wp.media({title:"Select Image",button:{text:"Use this image"},multiple:false});' .
			'fr.on("select",function(){' .
			'var a=fr.state().get("selection").first().toJSON();' .
			'u.value=a.url;' .
			'if(pi){pi.src=a.url;}' .
			'if(pv){pv.style.display="";}' .
			'if(rm){rm.style.display="";}' .
			'});' .
			'}' .
			'fr.open();' .
			'});' .
			'if(rm){' .
			'rm.addEventListener("click",function(e){' .
			'e.preventDefault();' .
			'u.value="";' .
			'if(pi){pi.src="";}' .
			'if(pv){pv.style.display="none";}' .
			'rm.style.display="none";' .
			'});' .
			'}' .
			'});' .
			'});';
	}
}
