<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Forms\Controls;

use Nette;
use Nette\Forms\IControl;
use Nette\Utils\Html;
use Nette\Forms\Form;


/**
 * Base class that implements the basic functionality common to form controls.
 *
 * @property-read Form $form
 * @property-read string $htmlName
 * @property   string $htmlId
 * @property   mixed $value
 * @property   bool $disabled
 * @property   bool $omitted
 * @property-read Html $control
 * @property-read Html $label
 * @property-read Html $controlPrototype
 * @property-read Html $labelPrototype
 * @property   bool $required
 * @property-read array $errors
 * @property-read string $error
 */
abstract class BaseControl extends Nette\ComponentModel\Component implements IControl
{
	/** @var string */
	public static $idMask = 'frm-%s';

	/** @var string textual caption or label */
	public $caption;

	/** @var mixed current control value */
	protected $value;

	/** @var Html  control element template */
	protected $control;

	/** @var Html  label element template */
	protected $label;

	/** @var array */
	private $errors = [];

	/** @var bool */
	protected $disabled = FALSE;

	/** @var bool|NULL */
	private $omitted;

	/** @var Nette\Forms\Rules */
	private $rules;

	/** @var Nette\Localization\ITranslator */
	private $translator = TRUE; // means autodetect

	/** @var array user options */
	private $options = [];


	/**
	 * @param  string  caption
	 */
	public function __construct($caption = NULL)
	{
		$this->monitor(Form::class);
		parent::__construct();
		$this->control = Html::el('input', ['type' => NULL, 'name' => NULL]);
		$this->label = Html::el('label');
		$this->caption = $caption;
		$this->rules = new Nette\Forms\Rules($this);
		$this->setValue(NULL);
	}


	/**
	 * This method will be called when the component becomes attached to Form.
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	protected function attached($form)
	{
		if (!$this->isDisabled() && $form instanceof Form && $form->isAnchored() && $form->isSubmitted()) {
			$this->loadHttpData();
		}
	}


	/**
	 * Returns form.
	 * @param  bool   throw exception if form doesn't exist?
	 * @return Form
	 */
	public function getForm($need = TRUE)
	{
		return $this->lookup(Form::class, $need);
	}


	/**
	 * Loads HTTP data.
	 * @return void
	 */
	public function loadHttpData()
	{
		$this->setValue($this->getHttpData(Form::DATA_TEXT));
	}


	/**
	 * Loads HTTP data.
	 * @return mixed
	 */
	public function getHttpData($type, $htmlTail = NULL)
	{
		return $this->getForm()->getHttpData($type, $this->getHtmlName() . $htmlTail);
	}


	/**
	 * Returns HTML name of control.
	 * @return string
	 */
	public function getHtmlName()
	{
		return Nette\Forms\Helpers::generateHtmlName($this->lookupPath(Form::class));
	}


	/********************* interface IControl ****************d*g**/


	/**
	 * Sets control's value.
	 * @return self
	 */
	public function setValue($value)
	{
		$this->value = $value;
		return $this;
	}


	/**
	 * Returns control's value.
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}


	/**
	 * Is control filled?
	 * @return bool
	 */
	public function isFilled()
	{
		$value = $this->getValue();
		return $value !== NULL && $value !== [] && $value !== '';
	}


	/**
	 * Sets control's default value.
	 * @return self
	 */
	public function setDefaultValue($value)
	{
		$form = $this->getForm(FALSE);
		if ($this->isDisabled() || !$form || !$form->isAnchored() || !$form->isSubmitted()) {
			$this->setValue($value);
		}
		return $this;
	}


	/**
	 * Disables or enables control.
	 * @param  bool
	 * @return self
	 */
	public function setDisabled($value = TRUE)
	{
		if ($this->disabled = (bool) $value) {
			$this->setValue(NULL);
		}
		return $this;
	}


	/**
	 * Is control disabled?
	 * @return bool
	 */
	public function isDisabled()
	{
		return $this->disabled === TRUE;
	}


	/**
	 * Sets whether control value is excluded from $form->getValues() result.
	 * @param  bool
	 * @return self
	 */
	public function setOmitted($value = TRUE)
	{
		$this->omitted = (bool) $value;
		return $this;
	}


	/**
	 * Is control value excluded from $form->getValues() result?
	 * @return bool
	 */
	public function isOmitted()
	{
		return $this->omitted || ($this->disabled && $this->omitted === NULL);
	}


	/********************* rendering ****************d*g**/


	/**
	 * Generates control's HTML element.
	 * @return Html|string
	 */
	public function getControl()
	{
		$this->setOption('rendered', TRUE);
		$el = clone $this->control;
		return $el->addAttributes([
			'name' => $this->getHtmlName(),
			'id' => $this->getHtmlId(),
			'required' => $this->isRequired(),
			'disabled' => $this->isDisabled(),
			'data-nette-rules' => Nette\Forms\Helpers::exportRules($this->rules) ?: NULL,
		]);
	}


	/**
	 * Generates label's HTML element.
	 * @param  string
	 * @return Html|string
	 */
	public function getLabel($caption = NULL)
	{
		$label = clone $this->label;
		$label->for = $this->getHtmlId();
		$label->setText($this->translate($caption === NULL ? $this->caption : $caption));
		return $label;
	}


	/**
	 * @return Nette\Utils\Html|NULL
	 */
	public function getControlPart()
	{
		return $this->getControl();
	}


	/**
	 * @return Nette\Utils\Html|NULL
	 */
	public function getLabelPart()
	{
		return $this->getLabel();
	}


	/**
	 * Returns control's HTML element template.
	 * @return Html
	 */
	public function getControlPrototype()
	{
		return $this->control;
	}


	/**
	 * Returns label's HTML element template.
	 * @return Html
	 */
	public function getLabelPrototype()
	{
		return $this->label;
	}


	/**
	 * Changes control's HTML id.
	 * @param  string new ID, or FALSE or NULL
	 * @return self
	 */
	public function setHtmlId($id)
	{
		$this->control->id = $id;
		return $this;
	}


	/**
	 * Returns control's HTML id.
	 * @return string
	 */
	public function getHtmlId()
	{
		if (!isset($this->control->id)) {
			$this->control->id = sprintf(self::$idMask, $this->lookupPath());
		}
		return $this->control->id;
	}


	/**
	 * Changes control's HTML attribute.
	 * @param  string name
	 * @param  mixed  value
	 * @return self
	 */
	public function setAttribute($name, $value = TRUE)
	{
		$this->control->$name = $value;
		return $this;
	}


	/********************* translator ****************d*g**/


	/**
	 * Sets translate adapter.
	 * @return self
	 */
	public function setTranslator(Nette\Localization\ITranslator $translator = NULL)
	{
		$this->translator = $translator;
		return $this;
	}


	/**
	 * Returns translate adapter.
	 * @return Nette\Localization\ITranslator|NULL
	 */
	public function getTranslator()
	{
		if ($this->translator === TRUE) {
			return $this->getForm(FALSE) ? $this->getForm()->getTranslator() : NULL;
		}
		return $this->translator;
	}


	/**
	 * Returns translated string.
	 * @param  mixed
	 * @param  int      plural count
	 * @return string
	 */
	public function translate($value, $count = NULL)
	{
		if ($translator = $this->getTranslator()) {
			$tmp = is_array($value) ? [& $value] : [[& $value]];
			foreach ($tmp[0] as & $v) {
				if ($v != NULL && !$v instanceof Html) { // intentionally ==
					$v = $translator->translate($v, $count);
				}
			}
		}
		return $value;
	}


	/********************* rules ****************d*g**/


	/**
	 * Adds a validation rule.
	 * @param  mixed      rule type
	 * @param  string     message to display for invalid data
	 * @param  mixed      optional rule arguments
	 * @return self
	 */
	public function addRule($validator, $message = NULL, $arg = NULL)
	{
		$this->rules->addRule($validator, $message, $arg);
		return $this;
	}


	/**
	 * Adds a validation condition a returns new branch.
	 * @param  mixed     condition type
	 * @param  mixed     optional condition arguments
	 * @return Nette\Forms\Rules      new branch
	 */
	public function addCondition($validator, $value = NULL)
	{
		return $this->rules->addCondition($validator, $value);
	}


	/**
	 * Adds a validation condition based on another control a returns new branch.
	 * @param  IControl form control
	 * @param  mixed      condition type
	 * @param  mixed      optional condition arguments
	 * @return Nette\Forms\Rules      new branch
	 */
	public function addConditionOn(IControl $control, $validator, $value = NULL)
	{
		return $this->rules->addConditionOn($control, $validator, $value);
	}


	/**
	 * @return Nette\Forms\Rules
	 */
	public function getRules()
	{
		return $this->rules;
	}


	/**
	 * Makes control mandatory.
	 * @param  mixed  state or error message
	 * @return self
	 */
	public function setRequired($value = TRUE)
	{
		$this->rules->setRequired($value);
		return $this;
	}


	/**
	 * Is control mandatory?
	 * @return bool
	 */
	public function isRequired()
	{
		return $this->rules->isRequired();
	}


	/**
	 * Performs the server side validation.
	 * @return void
	 */
	public function validate()
	{
		if ($this->isDisabled()) {
			return;
		}
		$this->cleanErrors();
		$this->rules->validate();
	}


	/**
	 * Adds error message to the list.
	 * @param  string  error message
	 * @return void
	 */
	public function addError($message)
	{
		$this->errors[] = $message;
	}


	/**
	 * Returns errors corresponding to control.
	 * @return string
	 */
	public function getError()
	{
		return $this->errors ? implode(' ', array_unique($this->errors)) : NULL;
	}


	/**
	 * Returns errors corresponding to control.
	 * @return array
	 */
	public function getErrors()
	{
		return array_unique($this->errors);
	}


	/**
	 * @return bool
	 */
	public function hasErrors()
	{
		return (bool) $this->errors;
	}


	/**
	 * @return void
	 */
	public function cleanErrors()
	{
		$this->errors = [];
	}


	/********************* user data ****************d*g**/


	/**
	 * Sets user-specific option.
	 * @return self
	 */
	public function setOption($key, $value)
	{
		if ($value === NULL) {
			unset($this->options[$key]);
		} else {
			$this->options[$key] = $value;
		}
		return $this;
	}


	/**
	 * Returns user-specific option.
	 * @return mixed
	 */
	public function getOption($key, $default = NULL)
	{
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}


	/**
	 * Returns user-specific options.
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

}
