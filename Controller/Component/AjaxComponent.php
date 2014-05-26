<?php
app::uses('Component', 'Controller/Component');

/**
 * Class AjaxComponent
 *
 * @author Jelmer Dröge
 * @copyright 2014 - Avolans.nl
 * @license MIT
 * @link https://github.com/JelmerD/AjaxComponent Github repository
 */
class AjaxComponent extends Component {

    /**
     * A local reference to the controller
     *
     * @var Controller
     */
    public $controller;

    /**
     * called before Controller::beforeFilter()
     *
     * @param Controller $controller
     */
    public function initialize(Controller $controller) {
        $this->controller = $controller;
        parent::initialize($this->controller);
    }

    /**
     * Setup the Ajax request.
     *
     * - Set debug to 0
     * - Set autorender to false
     * - Check if the request type is equal to the supplied $type
     * - disable any caching
     *
     * @param string $type The type to check the controller request against
     *
     * @return bool bool true, just because ...
     * @throws BadRequestException When the request type isn't equal to $type
     */
    public function start($type = 'ajax') {
        Configure::write('debug', 0);
        $this->controller->autoRender = false;
        $this->controller->layout = 'ajax';
        if (!$this->controller->request->is($type)) {
            throw new BadRequestException('Only ' . $type . ' calls are allowed');
        }
        $this->controller->disableCache();

        return true;
    }

    /**
     * parse_str the form and return the actual data. Use this when you sent serialized form data to the controller
     *
     * @param string      $rawForm The raw data as found in Controller->request->data('xxx')
     * @param bool|string $method  If a method is provided, the _method will be checked against that value (PUT, POST, DELETE or GET) (case sensitive)
     *
     * @return mixed The raw form data
     * @throws MethodNotAllowedException
     */
    public function parseForm($rawForm, $method = false) {
        parse_str($rawForm, $form);
        if ($method && $form['_method'] !== $method) {
            throw new MethodNotAllowedException('The form method ' . $form['_method'] . ' is not allowed');
        }
        # if a token is set, remove it. It has nothing to do with the data and from now on, it's not used
        if (isset($form['data']['_Token'])) {
            unset($form['data']['_Token']);
        }

        return $form['data'];
    }

    /**
     * A special method that does the validation for you when you are going to return it to the Validation.js class.
     * This method is needed because of the default return format when the Model->validateAssociated() is called.
     *
     * @param Model $Model The model you want to check the associated data for
     * @param array $data  The form data as prepared by the AjaxComponent::parseForm() method
     * @param array $aliases Aliases for models, this can be used when you want a certain "abstract" model name to be interpreted as something else.
     *
     * Example. if ContactLogModal is used in the view, but the actual model is called ContactLog, you have to set $aliases to:
     * [ContactLogModal => ContactLog]
     *
     * @return array|bool True on success, an array filled with the errors when a validation error occurred
     */
    public function validateAssociated(Model &$Model, $data, $aliases = array()) {
        if ($Model->validateAssociated($data)) {
            return true;
        }
        # the structure of these validationErrors is fucked up: [ fielda => foo, ModelB => [ fielda => bar, fieldb => lorum ] ]
        $tmp = $Model->validationErrors;
        $errors = array();
        # So, for every modelname in the data array, check if that key exists in the errors list.
        foreach (array_keys($data) as $m) {
            # error? remove it from the $tmp list so that we can ensure the last remaining data is of the primary model
            if (array_key_exists($m, $tmp)) {
                $errors[$m] = $tmp[$m];
                unset($tmp[$m]);
            }
        }

        # if the primary model has errors, throw it in as well under it's current name
        if (!empty($tmp)) {
            $errors[$Model->alias] = $tmp;
        }

        # now it should be: [ ModelA => [ fielda => foo ], ModelB => [ fielda => bar, fieldb => lorum ] ]
        return $errors;
    }

    /**
     * The normal end of an ajax call
     *
     * @param array  $data Just some extra data you might want to parse to the response (can be found in the 'data' key in the json response)
     * @param string $msg  The main message of the response
     * @param int    $code The status code to use
     *
     * @return string The Json encoded result including the success key and data
     */
    public function end($data = array(), $msg = null, $code = 200) {
        $this->controller->response->statusCode($code);

        return json_encode(compact('msg', 'code', 'data'));
    }

    /**
     * The abrupt end of an ajax call
     *
     * @param string $msg  The main message of the response
     * @param array  $data Just some extra data you might want to parse to the response (can be found in the 'data' key in the json response)
     * @param int    $code The status code to use
     *
     * @return string The Json encoded result including the success key and data
     */
    public function error($msg, $data = array(), $code = 400) {
        return $this->end($data, $msg, $code);
    }

    /**
     * Set an alias for a certain key.
     *
     * Ie. if ContactLogModal is used in the view, but the actual model is called ContactLog, you have to set $aliases to:
     * [ContactLogModal => ContactLog]
     *
     * @param $aliases
     * @param $data
     *
     * @return mixed
     */
    public function aliasing($aliases, $data) {
        foreach ($aliases as $alias => $actual) {
            $data[$actual] = $data[$alias];
            unset($data[$alias]);
        }
        return $data;
    }

    /**
     * The same as _aliasing, but reversed. So that the models are named as before again
     *
     * @param $aliases
     * @param $data
     *
     * @return mixed
     */
    public function antiAliasing($aliases, $data) {
        return $this->aliasing(array_flip($aliases), $data);
    }

}