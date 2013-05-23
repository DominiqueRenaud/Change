(function () {

	var app = angular.module('RbsChange');

	app.config(['$provide', function ($provide) {
		$provide.provider('RbsChange.UrlManager', ['RbsChange.Utils', function (Utils) {

			this.$get = function() {
				var urls = {};

				var register = function (modelName, url) {
					if (angular.isString(url)) {
						url = { 'form': url };
					}
					urls[modelName] = angular.extend(urls[modelName] || {}, url);
				};


				var getUrl = function (doc, name) {
					var model,
					    out;

					if (angular.isObject(doc) && angular.isDefined(doc.model)) {
						model = doc.model;
					} else if (angular.isString(doc)) {
						model = doc;
					} else {
						throw new Error("Could not determine the Model of the given parameter: " + doc + ". Please provide a Model name (String) or a Document object.");
					}

					if (urls.hasOwnProperty(model)) {
						out = urls[model];
						if (name && out.hasOwnProperty(name)) {
							return out[name];
						} else if (name === 'i18n' && out.hasOwnProperty('form')) {
							//console.warn("No 'i18n' URL found for ", doc, ". Used 'form' URL instead.");
							return out['form'];
						} else if (angular.isString(out)) {
							return out;
						}
						return null;
					}
					return null;
				};


				var replaceParams = function (urlTpl, paramsObj) {
					angular.forEach(urlTpl.match(/:(\w+)/g), function (match) {
						var value = paramsObj[match.slice(1)] || '';
						if (Utils.isDocument()) {
							value = value.id;
						}
						urlTpl = urlTpl.replace(new RegExp(match, 'g'), value);
					});
					// Replace multiple '/' by only one '/'.
					return urlTpl.replace(/\/+/g, '/');
				};


				var fixUrl = function (url) {
					// Remove starting and ending slashes
					if (url.charAt(url.length-1) === '/') {
						url = url.slice(0, -1);
					}
					if (url.charAt(0) === '/') {
						url = url.slice(1);
					}
					return url;
				};


				/**
				 * Returns the URL of the given Document or Model name.
				 *
				 * @description
				 * If a Document is provided, the parameters in the URL templates are replaced to return
				 * the full URL ready to be used.
				 * If a Model name is provided, the returned String is the URL template, with parameters,
				 * as defined in the Module's configuration.
				 *
				 * @param {Object|String} The Document object or a Model name.
				 * Can be one of:
				 * - `String`: Model name such as 'Change_Website_Page'.
				 * - `Object`: Document object with the required own property 'model'.
				 *
				 * @param {Object} Optional parameters object that will take precedence over the Document's properties.
				 *
				 * @param {String} Name of the URL to get.
				 *
				 * @returns {String} The URL of the provided element.
				 */
				var getNamedUrl = function (doc, params, name) {
					var url;

					// Allows second parameter to be the name of the rule to use if there are no parameters.
					if (angular.isDefined(params) && ! angular.isObject(params) && angular.isUndefined(name)) {
						name = params;
						params = {};
					}

					url = getUrl(doc, name || 'form');

					if (url === null) {
						return "javascript:;";
					}

					// If `doc` is a Document object, we try to replace the parameters in the `url` with
					// the corresponding properties of the Document.
					if (angular.isString(doc)) {
						if (angular.isObject(params)) {
							url = replaceParams(url, params);
						}
					} else {
						url = replaceParams(url, angular.extend({}, doc, params));
					}

					return fixUrl(url);
				};



				// Public API

				return {
					'register'   : register,

					'getListUrl' : function (doc, params) {
						return getNamedUrl(doc, params, 'list');
					},

					'getTreeUrl' : function (doc, params) {
						return getNamedUrl(doc, params, 'tree');
					},

					'getFormUrl' : function (doc, params) {
						return getNamedUrl(doc, params, 'form');
					},

					'getI18nUrl' : function (doc, LCID, fromLCID) {
						return getNamedUrl(doc, { 'LCID': LCID, 'fromLCID': fromLCID }, 'i18n');
					},

					'getUrl'     : function (doc, params, name) {
						return getNamedUrl(doc, params, name);
					}
				};
			};
		}]);

	}]);



	// Filters


	app.filter('documentURL', ['RbsChange.Breadcrumb', 'RbsChange.Utils', function (Breadcrumb, Utils) {

		return function (doc, urlName) {
			if ( ! Utils.isDocument(doc) ) {
				return 'javascript:;';
			}
			var	url = doc.url(urlName),
				node = Breadcrumb.getCurrentNode();
			if (urlName !== 'tree' && Utils.isTreeNode(node)) {
				url += '?tn=' + node.id;
			}
			return url;
		};

	}]);


	app.filter('documentI18nURL', ['RbsChange.UrlManager', '$log', function documentI18nURLFilter (UrlManager, $log) {

		return function (doc, LCID, fromLCID) {
			// This filter may be called while the `doc` is not loaded/instanciated yet.
			if (!doc || !doc.id) {
				return 'javascript:;';
			}
			try {
				return UrlManager.getI18nUrl(doc, LCID, fromLCID);
			} catch (e) {
				$log.error("Error while getting URL for: ", doc, LCID, fromLCID, e);
				return 'javascript:;';
			}
		};

	}]);

})();