(function ($) {

	var app = angular.module('RbsChange');

	app.provider('RbsChange.Actions', function RbsChangeActionsProvider() {
		this.$get = ['$http', '$filter', '$q', '$rootScope', 'RbsChange.Dialog', 'RbsChange.Clipboard', 'RbsChange.Utils', 'RbsChange.ArrayUtils', 'RbsChange.REST', 'RbsChange.NotificationCenter', 'RbsChange.Loading', function ($http, $filter, $q, $rootScope, Dialog, Clipboard, Utils, ArrayUtils, REST, NotificationCenter, Loading) {
			function Actions () {

				this.reset = function () {
					this.actions = {};
					this.actionsForModel = {
						'all': []
					};
					this.actionGroups = {};
				};

				this.reset();


				/**
				 * @param actionName The name of the action to be called.
				 * @param paramsObj A hash object (map) containing the action's arguments.
				 *
				 * @return A promise object.
				 */
				this.execute = function (actionName, paramsObj) {
					var method = '_' + actionName.replace('.', '-'),
					    promise,
					    actionObject;

					if (method in this.actions) {
						paramsObj = paramsObj || {};
						// Call the action with the correct parameters provided in the 'paramsObj' object.
						actionObject = this.actions[method];

						if (actionObject.loading) {
							Loading.start("Action " + (actionObject.label || actionName));
						}

						if (actionObject.__execFn) {
							promise = this.actions[method].__execFn.apply(
									this.actions[method],
									Utils.objectValues(paramsObj, this.actions[method].__execFnArgs)
								);
						}

						// Create a promise if the action did not create one, and resolve it right now.
						if (angular.isUndefined(promise)) {
							var defer = $q.defer();
							promise = defer.promise;
							defer.resolve();
						}

						if (actionObject.loading) {
							promise.then(function actionCallback () {
								Loading.stop();
								NotificationCenter.clear();
							}, function actionErrback(reason) {
								Loading.stop();
								NotificationCenter.error("L'action \"" + (actionObject.label || actionName) + "\" a échoué.", reason, paramsObj);
							});
						} else {
							promise.then(function actionCallback () {
								NotificationCenter.clear();
							}, function actionErrback(reason) {
								NotificationCenter.error("L'action \"" + (actionObject.label || actionName) + "\" a échoué.", reason, paramsObj);
							});
						}

						return promise;
					} else {
						throw new Error("Action '" + actionName + "' does not seem to exist. Please check 'actions.js'.");
					}
				};


				/**
				 * Checks whether the action identified by 'actionName' should be enabled when applied on the given documents.
				 *
				 * @param actionName The name of the action.
				 * @param $docs An array containing the documents on which the action may be applied.
				 * @param $DL The DocumentList object attached to the documents.
				 *
				 * @return true if the action is enabled, false otherwise.
				 */
				this.isEnabled = function (actionName, $docs, $DL) {
					var method = '_' + actionName.replace('.', '-');
					if (method in this.actions) {
						if ('isEnabled' in this.actions[method]) {
							return this.actions[method].isEnabled($docs, $DL);
						} else {
							return true;
						}
					} else {
						throw new Error("Action '" + actionName + "' does not seem to exist. Please check 'actions.js'.");
					}
				};


				/*
				 * Registers the given 'actionObject'.
				 *
				 * @param actionObject = {
				 *   models       // Full model name, array of full model names or '*' for an action that is available to all models.
				 *   label
				 *   description  // Used as tooltip
				 *   icon
				 *   selection    // integer, "min,max" (max=0 for no upper limit) or "+" for at least one document
				 *   execute      // required: Array with parameters name and function as the last item; should return a promise.
				 *   isEnabled()  // optional (defaults to true): should return a boolean value.
				 * }
				 */
				this.register = function (actionObject) {
					var method = '_' + actionObject.name.replace('.', '-'),
					    i,
					    m;

					if (method in this.actions) {
						throw new Error("Action '" + actionObject.name + "' already exists.");
					}

					if (angular.isDefined(actionObject.execute) && ! angular.isArray(actionObject.execute)) {
						throw new Error("'execute' member in action definition should be an Array: ['param1', 'param2', function (param1, param2) {...}].");
					}

					// Build the function that checks if the action can be enabled
					// according to the number of selected documents.

					var isSelectionOkFn = null;
					if (angular.isNumber(actionObject.selection)) {
						var count = actionObject.selection;
						isSelectionOkFn = function (docs) {
							return docs && docs.length === count;
						};
					} else if (angular.isString(actionObject.selection)) {
						if (actionObject.selection === '+') {
							isSelectionOkFn = function (docs) {
								return docs && docs.length > 0;
							};
						} else if (actionObject.selection.indexOf(',') !== -1) {
							var range = actionObject.selection.split(",");
							range[0] = parseInt(range[0].trim(), 10);
							range[1] = parseInt(range[1].trim(), 10);
							if (range[1] === 0) {
								isSelectionOkFn = function (docs) {
									return docs && docs.length >= range[0];
								};
							} else {
								isSelectionOkFn = function (docs) {
									return docs && docs.length >= range[0] && docs.length <= range[1];
								};
							}
						}
					}

					// Build the function that checks if the action can be enabled
					// according to the model of the selected documents.

					var isModelOkFn;
					if (angular.isArray(actionObject.models)) {
						isModelOkFn = function (docs) {
							var i;
							for (i=0 ; i<docs.length ; i++) {
								var args = actionObject.models;
								args.unshift(docs[i]);
								if ( ! Utils.isModel.apply(Utils, args) ) {
									return false;
								}
							}
							return true;
						};
					} else {
						isModelOkFn = function (docs) {
							var i;
							for (i=0 ; i<docs.length ; i++) {
								if ( ! Utils.isModel.apply(Utils, [docs[i], actionObject.models]) ) {
									return false;
								}
							}
							return true;
						};
					}

					// Redefine the 'isEnabled()' method of the actionObject by adding the
					// isSelectionOkFn() and isModelOkFn() calls.

					if (isSelectionOkFn) {
						if (angular.isUndefined(actionObject.isEnabled)) {
							actionObject.isEnabled = function(docs) {
								return isSelectionOkFn(docs) && isModelOkFn(docs);
							};
						} else if (angular.isFunction(actionObject.isEnabled)) {
							var isEnabledFn = actionObject.isEnabled;
							actionObject.isEnabled = function(docs, DL) {
								return isSelectionOkFn(docs) && isModelOkFn(docs) && isEnabledFn(docs, DL);
							};
						}
					}


					// Mark this action as available for the given models.

					if (angular.isArray(actionObject.models)) {
						for (i=0 ; i<actionObject.models.length ; i++) {
							m = actionObject.models[i];
							if (angular.isArray(this.actionsForModel[m])) {
								this.actionsForModel[m].push(actionObject);
							} else {
								this.actionsForModel[m] = [actionObject];
							}
						}
					} else {
						m = actionObject.models === '*' ? 'all' : actionObject.models;
						if (angular.isArray(this.actionsForModel[m])) {
							this.actionsForModel[m].push(actionObject);
						} else {
							this.actionsForModel[m] = [actionObject];
						}
					}

					if (actionObject.execute) {
						actionObject.__execFn = actionObject.execute.pop();
						actionObject.__execFnArgs = actionObject.execute;
					}

					this.actions[method] = actionObject;
				};


				this.get = function (actionName) {
					var method = '_' + actionName.replace('.', '-');
					if (method in this.actions) {
						return this.actions[method];
					}
					return null;
				};


				this.getActionsForModels = function () {
					var i,
					    model,
					    actions = [],
					    callback = function (actionObj) {
							actions.push(actionObj.name);
						};

					for (i=0 ; i<arguments.length ; i++) {
						model = arguments[i];
						if (model in this.actionsForModel) {
							angular.forEach(this.actionsForModel[model], callback);
						}
					}
					return actions;
				};


				this.getAllActionsForModels = function () {
					var actions = this.getActionsForAllModels();
					angular.forEach(this.getActionsForModels.apply(this, arguments), function (actionObj) {
						actions.shift(actionObj.name);
					});
					return actions;
				};


				this.getActionsForAllModels = function () {
					var actions = [];
					angular.forEach(this.actionsForModel['all'], function (actionObj) {
						actions.push(actionObj.name);
					});
					return actions;
				};


				this.group = function (groupName, chooserFn) {
					this.actionGroups[groupName] = chooserFn;
				};


				this.isGroup = function (groupName) {
					return groupName in this.actionGroups;
				};


				this.getActionForGroup = function (groupName, $docs) {
					return this.actionGroups[groupName].call(this, $docs);
				};



				// ====== Default actions ======


				/**
				 * Action: addToClipboard
				 * @param $docs Documents on which the action should be applied.
				 */
				this.register({
					name        : 'addToClipboard',
					models      : '*',
					description : "Ajouter les documents sélectionnés dans le Presse-papier",
					icon        : "icon-bookmark",
					selection   : "+",

					execute : ['$docs', function ($docs) {
						Clipboard.append($docs);
					}]
				});


				/**
				 * Action: delete
				 * @param $docs Documents on which the action should be applied.
				 * @param confirmMessage Additional message to be displayed in the confirmation dialog.
				 */
				this.register({
					name        : 'delete',
					models      : '*',
					description : "Supprimer les documents sélectionnés",
					icon        : "icon-trash",
					selection   : "+",
					cssClass    : "btn-danger-hover",

					execute : ['$docs', '$embedDialog', '$scope', '$target', 'confirmMessage', '$DL', function ($docs, $embedDialog, $scope, $target, confirmMessage, $DL) {
						var correction = false,
						    localized = false,
						    message;

						angular.forEach($docs, function (doc) {
							if (Utils.hasCorrection(doc)) {
								correction = true;
							}
							if (Utils.isLocalized(doc)) {
								localized = true;
							}
						});

						// TODO
						// If there are corrections and/or localizations, ask the user what should be deleted.

						message = "Vous êtes sur le point de supprimer " + $filter('documentListSummary')($docs) + ".";
						if (correction) {
							message += "<p>Certains documents ont des corrections en cours : seules les corrections seront supprimées.</p>";
						}
						confirmMessage = confirmMessage || null;

						var promise;
						if ($embedDialog) {
							promise = Dialog.confirmEmbed(
								$embedDialog,
								"Supprimer ?",
								message,
								$scope,
								{
									'pointedElement'    : $target,
									'primaryButtonClass': 'btn-danger',
									'primaryButtonText' : 'supprimer'
								}
							);
						} else {
							promise = Dialog.confirm(
									"Supprimer ?",
									message,
									"danger",
									confirmMessage
							);
						}

						promise.then(function () {
							var promises = [];
							// Call one REST request per document to remove and store the resulting Promise.
							angular.forEach($docs, function (doc) {
								promises.push(REST['delete'](doc));
							});
							// Refresh the list when all the requests have completed.
							$q.all(promises).then(function () {
								$DL.reload();
							});
						});
					}]
				});


				/**
				 * Action: activate
				 */
				this.register({
					name        : 'activate',
					models      : '*',
					label       : "Activer",
					description : "Activer les documents sélectionnés",
					icon        : "icon-play",
					selection   : "+",
					display     : "icon",
					loading     : true,

					execute : ['$docs', function ($docs) {

						var promises = [];
						// Call one REST request per document to activate and store the resulting Promise.
						angular.forEach($docs, function (doc) {
							var promise = REST.actionThenReload('activate', doc);
							promises.push(promise);
							promise.then(function (updatedDoc) {
								angular.extend(doc, updatedDoc);
							});
						});
						return $q.all(promises);

					}],

					isEnabled : function ($docs) {
						for (var i=0 ; i<$docs.length ; i++) {
							if ( ! Utils.hasStatus($docs[i], 'DEACTIVATED')) {
								return false;
							}
						}
						return true;
					}
				});


				/**
				 * Action: startValidation
				 */
				this.register({
					name        : 'startValidation',
					models      : '*',
					label       : "Publier",
					description : "Valider les documents sélectionnés",
					icon        : "icon-ok",
					selection   : "+",
					display     : "icon",
					loading     : true,

					execute : ['$docs', function ($docs) {

						var promises = [];
						// Call one REST request per document to activate and store the resulting Promise.
						angular.forEach($docs, function (doc) {
							var promise = REST.actionThenReload('startValidation', doc);
							promises.push(promise);
							promise.then(function (updatedDoc) {
								angular.extend(doc, updatedDoc);
							});
						});
						return $q.all(promises);

					}],

					isEnabled : function ($docs) {
						for (var i=0 ; i<$docs.length ; i++) {
							if ( ! Utils.hasStatus($docs[i], 'DRAFT') ) {
								return false;
							}
						}
						return true;
					}
				});


				/**
				 * Action: startPublication
				 */
				this.register({
					name        : 'startPublication',
					models      : '*',
					label       : "Publier",
					description : "Publier les documents sélectionnés",
					icon        : "icon-rss",
					selection   : "+",
					display     : "icon",
					loading     : true,

					execute : ['$docs', function ($docs) {

						var promises = [];
						// Call one REST request per document to activate and store the resulting Promise.
						angular.forEach($docs, function (doc) {
							var promise = REST.actionThenReload('startPublication', doc);
							promises.push(promise);
							promise.then(function (updatedDoc) {
								angular.extend(doc, updatedDoc);
							});
						});
						return $q.all(promises);

					}],

					isEnabled : function ($docs) {
						for (var i=0 ; i<$docs.length ; i++) {
							if ( ! Utils.hasStatus($docs[i], 'VALIDATION') ) {
								return false;
							}
						}
						return true;
					}
				});


				/**
				 * Action: save
				 */
				this.register({
					name        : 'save',
					models      : '*',
					label       : "Enregistrer",
					description : "Enregistrer les documents sélectionnés",
					icon        : "icon-ok",
					selection   : "+",
					display     : "icon",
					loading     : true,

					execute : ['$docs', '$currentTreeNode', function ($docs, $currentTreeNode) {

						var promises = [];
						// Call one REST request per document and store the resulting Promise.
						angular.forEach($docs, function (doc) {
							promises.push(REST.save(doc, $currentTreeNode));
						});
						return $q.all(promises);

					}]

				});


				/**
				 * Action: applyCorrection
				 */
				this.register({
					name        : 'applyCorrection',
					models      : '*',
					label       : "Appliquer la correction",
					description : "Appliquer la correction des documents sélectionnés",
					icon        : "icon-download-alt",
					selection   : "+",
					display     : "icon",

					execute : ['$docs', '$scope', '$embedDialog', '$target', function ($docs, $scope, $embedDialog, $target) {

						var doc = $docs[0];

						// TODO
						var q = $q.defer();

						Dialog.embed(
							$embedDialog,
							{
								'contents': "<div class='apply-correction-options'/>",
								'title': "Appliquer la correction ?"
							},
							$scope,
							{
								'pointedElement': $target
							}
						).then(function (when) {
							// FIXME
							// Corrections on documents in the list have not been loaded.
							// Well, this is a problem here because we don't have any information about the correction,
							// and thus we don't know what to do (which action to call).

							// First, load the Correction into the Document.
							REST.loadCorrection(doc).then(function (doc) {

								if (doc.META$.correction.status === 'DRAFT') {
									REST.action('startCorrectionValidation', doc).then(function (result) {
										doc.META$.correction.status = result.data['correction-status'];
										q.resolve(doc);
									});
								} else {
									REST.action('startCorrectionPublication', doc, {'publishImmediately': true}).then(function (result) {
										doc.META$.correction.status = result.data['correction-status'];
										if (doc.META$.correction.status === 'FILED') {
											delete doc.META$.correction;
										}
										q.resolve(doc);
									});
								}

							});

						});

						return q.promise;
					}],

					isEnabled : function ($docs) {
						return $docs.length === 1 && Utils.hasCorrection($docs[0]);
					}
				});


				/**
				 * Action: deactivate
				 * @param docs Documents on which the action should be applied.
				 */
				this.register({
					name        : 'deactivate',
					models      : '*',
					label       : "Désactiver",
					description : "Désactiver les documents sélectionnés",
					icon        : "icon-pause",
					selection   : "+",
					display     : "icon",
					loading     : true,

					execute : ['$docs', function ($docs) {

						var promises = [];
						// Call one REST request per document to remove and store the resulting Promise.
						angular.forEach($docs, function (doc) {
							var promise = REST.actionThenReload('deactivate', doc);
							promises.push(promise);
							promise.then(function (updatedDoc) {
								doc.publicationStatus = updatedDoc.publicationStatus;
							});
						});
						return $q.all(promises);

					}],

					isEnabled : function ($docs) {
						for (var i=0 ; i<$docs.length ; i++) {
							if ( ! Utils.hasStatus($docs[i], 'ACTIVE', 'PUBLISHABLE') ) {
								return false;
							}
						}
						return true;
					}
				});



				/**
				 * Action: reorder
				 */
				this.register({
					name        : 'reorder',
					models      : '*',
					label       : "Réorganiser",
					description : "Réorganiser les éléments de la liste ci-dessous",
					icon        : "icon-reorder",
					display     : "icon+label",

					execute : ['$DL', '$scope', '$embedDialog', '$target', function ($DL, $scope, $embedDialog, $target) {
						$DL.toggleSort('nodeOrder', true);
						Dialog.embed(
							$embedDialog,
							{
								'contents' : '<reorder-panel documents="DL.documents"></reorder-panel>',
								'title'    : "<i class=\"" + this.icon + "\"></i> Réorganisation des éléments"
							},
							$scope,
							{
								'pointedElement': $target
							}
						);
					}],

					isEnabled : function ($docs, $DL) {
						return $DL.hasColumn('nodeOrder') && $DL.documents && $DL.documents.length > 1;
					}

				});


				this.group('groupPublishDocument', function (docs) {
					var status = docs.length ? docs[0].publicationStatus : null;

					for (var i=1 ; i<docs.length ; i++) {
						if (docs[i].publicationStatus !== status) {
							status = null;
							break;
						}
					}

					switch (status) {
						case 'DRAFT':
							return this.get('startValidation');
						case 'VALIDATION':
							return this.get('startPublication');
						default:
							return this.get('activate');
					}
				});


			}

			return new Actions();

		}];
	});


})( window.jQuery );