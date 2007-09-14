<?php

/**
 * Log-in form for the "member" authentication method
 */



/**
 * Log-in form for the "member" authentication method
 */
class MemberLoginForm extends LoginForm {

	/**
	 * Constructor
	 *
	 * @param Controller $controller The parent controller, necessary to
	 *                               create the appropriate form action tag.
	 * @param string $name The method on the controller that will return this
	 *                     form object.
	 * @param FieldSet|FormField $fields All of the fields in the form - a
	 *                                   {@link FieldSet} of {@link FormField}
	 *                                   objects.
	 * @param FieldSet|FormAction $actions All of the action buttons in the
	 *                                     form - a {@link FieldSet} of
	 *                                     {@link FormAction} objects
	 * @param bool $checkCurrentUser If set to TRUE, it will be checked if a
	 *                               the user is currently logged in, and if
	 *                               so, only a logout button will be rendered
	 */
	function __construct($controller, $name, $fields = null, $actions = null,
											 $checkCurrentUser = true) {

		$customCSS = project() . '/css/member_login.css';
		if(Director::fileExists($customCSS)) {
			Requirements::css($customCSS);
		}

		if(isset($_REQUEST['BackURL'])) {
			$backURL = $_REQUEST['BackURL'];
		} else {
			$backURL = Session::get('BackURL');
		}

		if($checkCurrentUser && Member::currentUserID()) {
			$fields = new FieldSet();
			$actions = new FieldSet(new FormAction("logout",
																						 "Log in as someone else"));
		} else {
			if(!$fields) {
				$fields = new FieldSet(
					new HiddenField("AuthenticationMethod", null, "Member", $this),
					new TextField("Email", "Email address",
						Session::get('SessionForms.MemberLoginForm.Email'), null, $this),
					new EncryptField("Password", "Password", null, $this),
					new CheckboxField("Remember", "Remember me next time?",
						Session::get('SessionForms.MemberLoginForm.Remember'), $this)
				);
			}
			if(!$actions) {
				$actions = new FieldSet(
					new FormAction("dologin", "Log in"),
					new FormAction("forgotPassword", "I've lost my password")
				);
			}
		}

		if(isset($backURL)) {
			$fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
		}

		parent::__construct($controller, $name, $fields, $actions);
	}


	/**
	 * Get message from session
	 */
	protected function getMessageFromSession() {
		parent::getMessageFromSession();
		if(($member = Member::currentUser()) &&
				!Session::get('MemberLoginForm.force_message')) {
			$this->message = "You're logged in as $member->FirstName.";
		}
		Session::set('MemberLoginForm.force_message', false);
	}


	/**
	 * Login form handler method
	 *
	 * This method is called when the user clicks on "Log in"
	 *
	 * @param array $data Submitted data
	 */
	public function dologin($data) {
		if($this->performLogin($data)) {
			Session::clear('SessionForms.MemberLoginForm.Email');
			Session::clear('SessionForms.MemberLoginForm.Remember');

			if($backURL = $_REQUEST['BackURL']) {
				Session::clear("BackURL");
				Director::redirect($backURL);
			} else
				Director::redirectBack();

		} else {
			Session::set('SessionForms.MemberLoginForm.Email', $data['Email']);
			Session::set('SessionForms.MemberLoginForm.Remember',
									 isset($data['Remember']));
			if($badLoginURL = Session::get("BadLoginURL")) {
				Director::redirect($badLoginURL);
			} else {
				Director::redirectBack();
			}
		}
	}


	/**
	 * Log out form handler method
	 *
	 * This method is called when the user clicks on "logout" on the form
	 * created when the parameter <i>$checkCurrentUser</i> of the
	 * {@link __construct constructor} was set to TRUE and the user was
	 * currently logged in.
	 */
	public function logout() {
		$s = new Security();
		$s->logout();
	}


  /**
   * Try to authenticate the user
   *
   * @param array Submitted data
   * @return Member Returns the member object on successful authentication
   *                or NULL on failure.
   */
	public function performLogin($data) {
		if($member = MemberAuthenticator::authenticate($data, $this)) {
			$firstname = Convert::raw2xml($member->FirstName);
			Session::set("Security.Message.message", "Welcome Back, {$firstname}");
			Session::set("Security.Message.type", "good");

			$member->LogIn(isset($data['Remember']));
			return $member;

		} else {
			return null;
		}
	}


	/**
	 * Forgot password form handler method
	 *
	 * This method is called when the user clicks on "I've lost my password"
	 *
	 * @param array $data Submitted data
	 */
	function forgotPassword($data) {
		$SQL_data = Convert::raw2sql($data);

		if($data['Email'] && $member = DataObject::get_one("Member",
				"Member.Email = '$SQL_data[Email]'")) {
			if(!$member->Password) {
				$member->createNewPassword();
				$member->write();
			}

			$member->sendInfo('forgotPassword');
			Director::redirect('Security/passwordsent/' . urlencode($data['Email']));

		} else if($data['Email']) {
			$this->sessionMessage(
				"Sorry, but I don't recognise the email address. Maybe you need to sign up, or perhaps you used another email address?",
				"bad");
			Director::redirectBack();

		} else {
			Director::redirect("Security/lostpassword");

		}
	}


	/**
   * Get the authenticator class
   *
   * @return Authenticator Returns the authenticator class for this login
   *                       form.
   */
  public static function getAuthenticator() {
		return new MemberAuthenticator;
	}
}


?>