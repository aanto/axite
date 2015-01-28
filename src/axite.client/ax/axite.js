define(
	["ax/ax", "ax/template", "ax/observer", "ax/templateHypernav", "ax/templateI18n"],
	function (ax, Template, Observer, TemplateHypernav, TemplateI18n) {

	/** AXITE model */
	var Axite = function () {
		ax.extend(this, Observer.prototype)

		this._mrqMessages = []
		this._mrqMessagesFilter = {}

		this.ui = new AxiteUi(this)
		this.ui.navigate(false,  this.getCurrentPageKey())
	}


	Axite.prototype = {

		getCurrentPageKey: function () {
			return decodeURIComponent(document.location.pathname === "/" ? "/index" : document.location.pathname)
		},

		requestValue         : function (key       ) {this._mrq({event: "get",               key: key               }); return true},
		requestCollectionKeys: function (key       ) {this._mrq({event: "getCollectionKeys", key: key               }); return true},
		sendValue            : function (key, value) {this._mrq({event: "set",               key: key, value: value }); return true},
		deleteEntity         : function (key       ) {this._mrq({event: "delete",            key: key               }); return true},
		moveEntity           : function (key, dst  ) {this._mrq({event: "move",              key: key, dst: dst     }); return true},

		/* AJAX multirequest */
		_mrq: function (query) {
			if (isSystemCall(query.key)) {
				return undefined
			}

			if (query.key.contains("$")) {
				console.warn("Possibly illegal query ", query)
			}

			// To avoid excessive amount of .bind(this) inside
			var model = this

			// Filter out duplicated queries
			if (model._mrqMessagesFilter[JSON.stringify(query)]) {
				return undefined
			}

			model._mrqMessagesFilter[JSON.stringify(query)] = true
			model._mrqMessages.push(query)
			ax.resetTimeout(request, 1, "axite_mrq")

			return undefined


			function isSystemCall (key) {
				return key.substr(0, 1) === "_" || key.substr(0, 3) === "ax/" || key.substr(0, 6) === "axite/"
			}

			function request () {
				ax.request("/--mrq/", ax.requestHandle(handle), {messages: JSON.stringify(model._mrqMessages, null, " ")})
				model._mrqMessages = []
				model._mrqMessagesFilter = {}
			}

			function handle(response) {
				model.emit("setBulk", response)
				ax.foreach(response, function (message) {
					switch (message.event) {
						// case "set":	              model.emit("setValue",          message.key, message.data); break
						case "setSuccess":        model.emit("setValueSuccess",   message.key); break
						case "setFail":           model.emit("setValueFail",      message.key, message.message); break
						// case "setCollectionKeys": model.emit("setCollectionKeys", message.key, message.data); break
						case "deleteSuccess":     model.emit("deleteSuccess",     message.key); break
						case "deleteFail":        model.emit("deleteFail",        message.key, message.message); break
						case "moveSuccess":       model.emit("moveSuccess",       message.key, message.dst); break
						case "moveFail":          model.emit("moveFail",          message.key, message.message); break
					}
				})
			}
		}
	}


	var AxiteUi = function(model) {
		this.model = model
		if (this.isAdmin()) {
			this.initAdminMode()
		}
	}

	AxiteUi.prototype = {
		initAdminMode: function() {
			// Init OSD
			this.showAdminOSD("view")

			// Page edit/preview mode
			this.model.on("switchEditableMode", this.switchEditableMode.bind(this))

			// Change save button appearance
			this.model.on("setValueSuccess", this.markSaveButton.bind(this, "saved"))
			this.model.on("setValueFail",    this.handleError.bind(this))
			this.model.on("deleteFail",      this.handleError.bind(this))
			this.model.on("moveFail",        this.handleError.bind(this))
		},

		isAdmin: function() {
			return !!document.getElementById("ax_adminOSD")
		},

		navigate: function (event, url) {
			if (url === "/") {
				url = "/index"
			}

			// History API
			if (event !== false) {
				event.preventDefault()
				window.history.pushState(null, null, url)
			}

			ax.element(window).once("popstate", function (event) {
				this.navigate(false, event.target.location.pathname)
			}.bind(this))

			// Init templates engine
			if (!this.template) {
				this.template = this.initTemplate()
			}

			this.template.refresh(false, {page: url})
		},

		initTemplate: function () {
			var admin = this.isAdmin()
			var model = this.model
			var template = new Template(document.documentElement).plugin(TemplateHypernav).plugin(TemplateI18n)

			template.willTrigger(model, "requestValue", "requestCollectionKeys")
			template.willTrigger(this, "navigate")

			// Bind model listeners
			model.willTrigger(template, "emit", "setBulk", "setValue", "setCollectionKeys")

			if (admin) {
				template.on("listItemSwitchedEditable", this.renderEditableListItemControls.bind(this))
				template.on("elementSwitchedEditable", this.renderEditableElementControls.bind(this))
				template.on("documentChanged", this.markSaveButton.bind(this, "changed"))

				model.willTrigger(template, "emit",
					"setValueSuccess",
					"setValueFail",
					"deleteFail",
					"moveSuccess",
					"moveFail",
					"markDelete",
					{"deleteSuccess": "remove"}
				)

				// Bind RTE listeners
				template.willTrigger(this, {
					"contentEditableFocus" : "showRtePanel",
					"contentEditableBlur"  : "hideRtePanel",
				})
			}

			return template
		},

		switchEditableMode: function (mode) {
			require(["ax/templateEditor"], function (TemplateEditor) {
				this.template.plugin(TemplateEditor, "editor")
				setTimeout(this.template.editor.switchEditableMode.bind(this.template.editor, mode), 0)
				this.showAdminOSD(mode)
			}.bind(this))
		},

		showLoginOSD: function() {
			ax.element("ax_adminLoginButton").addClass("hidden")
			ax.element("ax_adminLogin").removeClass("hidden").element.elements[0].focus()
		},

		showAdminOSD: function (mode) {
			ax.element(document.body)
			.condClass(mode === "view", "axMode-view")
			.condClass(mode === "edit", "axMode-edit")
		},

		markSaveButton: function (how) {
			var button = ax.element("ax_ButtonSave")

			button.removeClass("saved").removeClass("changed").addClass(how)
			switch (how) {
				case "changed": button.setText("Save"); break
				case "error": button.setText("Can't save!"); break
				case "saved": button.setText("Saved"); break
			}
		},

		/* Extra controls */
		renderEditableListItemControls: function (el) {
			/* List item ([x] public) */
			// if (el.tagName.match(/^SPAN|H1|H2|H3|H4|H5|H6|A|INPUT|SELECT|OPTION$/)) return
			if (el.hasAttribute("public")) {
				var controlsElement = document.getElementById("ax_ItemControls").cloneNode(true)
				controlsElement.id = ""
				this.template.editor.setExtraControls(el, controlsElement)
			}
		},

		renderEditableElementControls: function (el) {
			/* Image (dimensions) */
			if (el.tagName === "IMG" && el.parentNode.hasAttribute("ax:list")) {
				var controlsElement = document.getElementById("ax_ItemControlsImg").cloneNode(true)
				controlsElement.id = ""
				this.template.refresh(controlsElement, el.variables)
				el.setExtraControls(controlsElement)
			}
		},

		/* RTE */
		showRtePanel: function(el) {
			setTimeout(function () {
				var isReduced = el.tagName.match(/^P|BLOCKQUOTE$/)
				var isInvisible = el.tagName.match(/^SPAN|H1|H2|H3|H4|H5|H6|A|INPUT|SELECT$/)
				ax.element("ax_rteOSD").condClass(isReduced, "reduced").condClass(isInvisible, "invisible").condClass(!isInvisible, "visible")
			}, 100)
		},

		hideRtePanel: function () {
			setTimeout(function () {
				ax.element("ax_rteOSD").addClass("invisible").removeClass("visible")
			}, 100)
		},

		rteExec: function (command) {
			var value = null

			switch (command) {
				case "insertImage": {
					value = prompt("Please enter image url");
					if (value === null) {
						return;
					}
					break;
				}

				case "createLink": {
					value = prompt("Please enter destination url");
					if (value === null) {
						return;
					}
					if (value === "") {
						command = "unlink";
					}
					break;
				}
			}

			document.execCommand(command, false, value)
			ax.element(window.getSelection().focusNode.parentElement).emit("change")

			setTimeout(function () {
				ax.element("ax_rteOSD").addClass("visible").removeClass("invisible")
			}, 100)
		},

		/* CRUD controllers */
		saveAll: function () {
			// save
			ax.foreach(document.body.getElementsByClassName("changed"), function (el) {
				var key = this.template.getKey(el)
				if (key) {
					this.model.sendValue(key, this.template.getValue(el))
				}
			}.bind(this))

			// delete
			ax.foreach(document.body.getElementsByClassName("delete"), function (el) {
				var key = this.template.getKey(el)
				if (key) {
					this.model.deleteEntity(key)
				}
			}.bind(this))

			setTimeout(this.switchEditableMode.bind(this, "view"), 0)
		},

		deleteEntity: function (key) {
			if (confirm("Are you sure?".i18n())) {
				this.model.deleteEntity(key)
			}
		},

		moveEntity: function (key) {
			var newKey = prompt("Please enter new URL".i18n(), key)
			if (!newKey || newKey === key) {
				return undefined
			}
			this.model.moveEntity(key, newKey)
		},

		copyEntity: function (key) {
			var newKey = prompt("Please enter new URL".i18n(), key)
			if (!newKey || newKey === key) {
				return undefined
			}

			ax.foreach(document.body.getElementsByTagName("*"), function (el) {
				var entityKey = this.template.getEntityKey(el)
				var attr = this.template.getAttributeKey(el)

				if (entityKey === this.getCurrentPageKey()) {
					this.model.sendValue(newKey + this.template.ATTR_SEP + attr, this.template.getValue(el))
				}
			}.bind(this))
		},

		handleError: function(key, message) {
			this.markSaveButton("error")
			ax.element("ax_systemMessage").addClass("error").setText(message)
		},
	}

	return Axite

})