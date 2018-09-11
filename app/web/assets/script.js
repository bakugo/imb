(function () {
	var imb = window.imb = {};
	
	imb.data = {};
	imb.appData = {};
	imb.scriptSettings = {};
	imb.pageData = {};
	
	imb.main = function () {
		$(function () {
			if (imb.appData.name) {
				console.log(">>", imb.appData.name, imb.appData.version);
			}
			
			imb.scriptSettings = $.extend({
				enableTwemoji: true,
				enableThreadUpdater: true,
				enableDynamicPostForm: true,
				enableThreadStats: true
			}, imb.scriptSettings);
			
			imb.utils.runAllModules();
			imb.utils.processExistingPosts();
		});
	};
	
	imb.modules = {
		rootClasses: function () {
			this.active = true;
			
			document.documentElement.classList.add("javascript");
			document.documentElement.classList.add(navigator.userAgent.match(/Mobi/) ? "mobile" : "desktop");
		},
		
		twemoji: function () {
			var parse;
			
			if (!imb.scriptSettings.enableTwemoji || !window.twemoji) {
				return;
			}
			
			this.active = true;
			
			parse = function (target) {
				twemoji.parse(target, {size: 72});
			};
			
			parse(document.body);
			
			imb.utils.addPostProcessor(function (post) {
				parse(post);
			});
		},
		
		postSubmitRedirect: function () {
			var redirectTime;
			
			if (!document.body.classList.contains("postsubmit")) {
				return;
			}
			
			this.active = true;
			
			if (parseInt(document.body.dataset.success)) {
				redirectTime = document.body.dataset.redirectTime;
				redirectTime = parseFloat(redirectTime);
				redirectTime = (!isNaN(redirectTime) ? redirectTime : 1.0);
				
				setTimeout(function () {
					location = document.body.dataset.redirectTo;
				}, (redirectTime * 1000));
			}
		},
		
		quoteLinkProcessor: function () {
			if (!document.body.classList.contains("board-thread")) {
				return;
			}
			
			this.active = true;
			
			imb.utils.addPostProcessor(function (post) {
				var comment;
				var quoteLinks;
				var quoteLink;
				
				comment = post.querySelector(".content .comment");
				
				if (!comment) {
					return;
				}
				
				quoteLinks = comment.querySelectorAll(".quote-link");
				
				for (var i = 0; i < quoteLinks.length; i++) {
					quoteLink = quoteLinks[i];
					
					if (quoteLink.classList.contains("to-post")) {
						if (quoteLink.dataset.board === document.body.dataset.board) {
							if (document.querySelector(".thread .post[data-number=\"" + quoteLink.dataset.number + "\"]")) {
								quoteLink.href = ("#p" + quoteLink.dataset.number);
							}
						}
					}
				}
			});
		},
		
		replyLinks: function () {
			var replyLinkClick;
			
			if (!document.body.classList.contains("board-thread")) {
				return;
			}
			
			commentField = document.querySelector(".post-form textarea[name=comment]");
			
			if (!commentField) {
				return;
			}
			
			this.active = true;
			
			replyLinkClick = function (event) {
				var postNumber;
				var quoteLinkString;
				var beforeAndAfter;
				
				postNumber = event.target.textContent;
				quoteLinkString = (">>" + postNumber);
				
				if (!isNaN(commentField.selectionStart)) {
					beforeAndAfter = [
						commentField.value.substring(0, commentField.selectionStart),
						commentField.value.substring(commentField.selectionEnd, commentField.value.length)
					];
				} else {
					beforeAndAfter = ["", ""];
				}
				
				beforeAndAfter[0] = (beforeAndAfter[0] + (beforeAndAfter[0].match(/(^$|\n$)/) ? "" : "\n"));
				beforeAndAfter[1] = ((beforeAndAfter[1].match(/^\n/) ? "" : "\n") + beforeAndAfter[1]);
				
				commentField.value = (beforeAndAfter[0] + quoteLinkString + beforeAndAfter[1]);
				commentField.focus();
			};
			
			imb.utils.addPostProcessor(function (post) {
				var replyLink;
				
				replyLink = post.querySelector(".info .number .reply-link");
				
				if (replyLink) {
					replyLink.addEventListener("click", replyLinkClick);
				}
			});
		},
		
		threadUpdater: function () {
			var update;
			var updateTimeout;
			var eThread;
			var latestNumber;
			var latestTime;
			var threadState;
			
			if (!imb.scriptSettings.enableThreadUpdater) {
				return;
			}
			
			if (!document.body.classList.contains("board-thread")) {
				return;
			}
			
			eThread = document.querySelector(".board-content .thread");
			
			if (!eThread) {
				return;
			}
			
			this.active = true;
			
			this.update = update = function (myPost) {
				if (updateTimeout === null) {
					return;
				}
				
				clearTimeout(updateTimeout);
				updateTimeout = null;
				
				$.ajax({
					url: imb.pageData.threadUpdater.updateLocation,
					method: "get",
					dataType: "json",
					
					data: {
						"json": "1",
						"render": "1",
						"after_number": latestNumber,
						"after_time": latestTime
					},
					
					success: function (data, textStatus, jqXHR) {
						var posts;
						var post;
						var ePost;
						var ePostExisting;
						
						posts = [];
						posts.push(data.thread.post);
						posts = posts.concat(data.thread.replies);
						
						for (var i = 0; i < posts.length; i++) {
							post = posts[i];
							
							if (post.number > latestNumber) {
								latestNumber = post.number;
							}
							
							if (post.time_modified > latestTime) {
								latestTime = post.time_modified;
							}
							
							ePostExisting = eThread.querySelector(".post[data-number=\"" + post.number + "\"]");
							
							if (ePostExisting) {
								if (post.time_modified <= parseInt(ePostExisting.dataset.timeModified)) {
									if (!((post.number === post.thread) && (JSON.stringify(threadState) !== JSON.stringify(data.thread.state)))) {
										continue;
									}
								}
							}
							
							ePost = imb.utils.makePostFromHTML(post.html);
							
							imb.utils.processPost(ePost);
							
							if (ePostExisting) {
								eThread.replaceChild(ePost, ePostExisting);
							} else {
								eThread.appendChild(ePost);
							}
						}
						
						threadState = data.thread.state;
						
						if (myPost) {
							if (latestNumber >= myPost) {
								location.hash = ("#p" + myPost);
							}
						}
						
						if (data.thread.stats && imb.modules.threadStats.active) {
							imb.modules.threadStats.setThreadStats(data.thread.stats);
						}
						
						updateTimeout = setTimeout(update, 10000);
					},
					
					error: function (jqXHR, textStatus, errorThrown) {
						updateTimeout = setTimeout(update, 20000);
					}
				});
			};
			
			latestNumber = imb.pageData.threadUpdater.latestNumber;
			latestTime = imb.pageData.threadUpdater.latestTime;
			threadState = imb.pageData.threadUpdater.threadState;
			
			imb.utils.addBoardLinksItem("Update", update);
			
			updateTimeout = setTimeout(update, 8000);
		},
		
		targetPost: function () {
			var checkTarget;
			var setTarget;
			
			if (!document.body.classList.contains("board-thread")) {
				return;
			}
			
			this.active = true;
			
			checkTarget = function () {
				var hashMatch;
				
				hashMatch = location.hash.match(/^#p(\d+)$/);
				
				if (hashMatch) {
					setTarget(parseInt(hashMatch[1]));
				} else {
					setTarget(null);
				}
			};
			
			this.setTarget = setTarget = function (postNumber) {
				var lastTarget;
				var nextTarget;
				
				lastTarget = document.querySelector(".thread .post.target");
				
				if (lastTarget) {
					lastTarget.classList.remove("target");
				}
				
				if (postNumber) {
					nextTarget = document.querySelector(".thread .post[data-number=\"" + postNumber + "\"]");
					
					if (nextTarget) {
						nextTarget.classList.add("target");
					}
				}
			};
			
			checkTarget();
			
			window.addEventListener("hashchange", function () {
				checkTarget();
			});
		},
		
		dynamicPostForm: function () {
			var postForm;
			var postFormJson;
			var postFormFile;
			var postFormSubmit;
			var postFormSubmitOriginalText;
			var postFormIsSubmitting;
			var resetForm;
			
			if (!imb.scriptSettings.enableDynamicPostForm) {
				return;
			}
			
			if (!document.body.classList.contains("board")) {
				return;
			}
			
			postForm = document.querySelector(".posting .post-form");
			
			if (!postForm) {
				return;
			}
			
			this.active = true;
			
			resetForm = function () {
				var inputs;
				var input;
				
				inputs = postForm.querySelectorAll("input, textarea");
				
				for (var i = 0; i < inputs.length; i++) {
					input = inputs[i];
					
					if (input.hidden || input.type === "hidden" || input.readonly) {
						continue;
					}
					
					if (input.name === "name") {
						continue;
					}
					
					if (input.type === "checkbox") {
						input.checked = false;
					} else {
						input.value = null;
					}
				}
			};
			
			postFormJson = postForm.querySelector("input[name=json]");
			postFormFile = postForm.querySelector("input[name=file]");
			postFormSubmit = postForm.querySelector("input[type=submit]");
			
			postFormSubmitOriginalText = postFormSubmit.value;
			
			postFormIsSubmitting = false;
			
			$(postFormSubmit).on("click", function (event) {
				var formData;
				
				event.preventDefault();
				
				if (postFormIsSubmitting) {
					return;
				}
				
				postFormIsSubmitting = true;
				
				postFormSubmit.value = "Connecting...";
				
				postFormJson.dataset.originalValue = postFormJson.value;
				postFormJson.value = "1";
				
				formData = new FormData(postForm);
				
				postFormJson.value = postFormJson.dataset.originalValue;
				delete postFormJson.dataset.originalValue;
				
				$.ajax({
					url: postForm.action,
					method: postForm.method,
					contentType: false,
					processData : false,
					cache: false,
					data: formData,
					
					xhr: function () {
						var xhr;
						
						xhr = jQuery.ajaxSettings.xhr();
						
						if (xhr instanceof XMLHttpRequest) {
							xhr.upload.addEventListener("progress", function (event) {
								if ((postFormFile && postFormFile.value) && event.lengthComputable) {
									postFormSubmit.value = ("Posting... (" + (Math.round((event.loaded / event.total) * 100) + "%") + ")");
								} else {
									postFormSubmit.value = "Posting...";
								}
							}, false);
						}
						
						return xhr;
					},
					
					success: function (data, textStatus, jqXHR) {
						if (data instanceof Object) {
							if (data.success) {
								if (data.info.post.thread) {
									if (imb.modules.threadUpdater.active) {
										imb.modules.threadUpdater.update(data.info.post.number);
									}
								} else {
									location = data.info.post.url;
								}
								
								resetForm();
							} else {
								if (data.info.banned) {
									location = data.info.banned_url;
								} else {
									alert(data.message);
								}
							}
							
							if (window.grecaptcha) {
								window.grecaptcha.reset();
							}
						} else {
							alert("Unknown server error while posting.");
						}
					},
					
					error: function (jqXHR, textStatus, errorThrown) {
						alert("Connection error while posting.");
					},
					
					complete: function (jqXHR, textStatus) {
						postFormSubmit.value = postFormSubmitOriginalText;
						postFormIsSubmitting = false;
					}
				});
			});
		},
		
		thumbLazyLoad: function () {
			var process;
			var options;
			
			if (!$.fn.lazyload) {
				return;
			}
			
			this.active = true;
			
			process = function (target) {
				target.querySelectorAll("img.lazyload").forEach(function (item) {
					$(item).lazyload(options);
					item.classList.add("lazyloaded");
				});
			};
			
			options = {
				data_attribute: "src",
				threshold: 1000,
				placeholder: null
			};
			
			imb.utils.addPostProcessor(function (post) {
				process(post);
			});
			
			document.querySelectorAll(".catalog-thread").forEach(function (item) {
				process(item);
			});
		},
		
		threadStats: function () {
			var eThreadStats;
			var eThreadStatsPosts;
			var eThreadStatsFiles;
			var eThreadStatsPosters;
			var setThreadStats;
			
			if (!imb.scriptSettings.enableThreadStats) {
				return;
			}
			
			if (!document.body.classList.contains("board-thread")) {
				return;
			}
			
			this.active = true;
			
			this.setThreadStats = setThreadStats = function (data) {
				var dataStr;
				
				dataStr = {
					posts: (!isNaN(data.posts) ? data.posts : "?"),
					files: (!isNaN(data.files) ? data.files : "?"),
					posters: (data.posters ? data.posters : "?"),
					page: (data.page ? data.page : "?")
				};
				
				eThreadStats.title = ("Posts: " + dataStr.posts + " / Files: " + dataStr.files + " / Posters: " + dataStr.posters + " / Page: " + dataStr.page);
				eThreadStats.textContent = (dataStr.posts + " / " + dataStr.files + " / " + dataStr.posters + " / " + dataStr.page);
				
				eThreadStats.style.display = "";
			};
			
			eThreadStats = document.createElement("div");
			eThreadStats.id = "thread-stats";
			
			eThreadStats.style.display = "none";
			eThreadStats.style.cursor = "default";
			eThreadStats.style.position = "fixed";
			eThreadStats.style.bottom = "4px";
			eThreadStats.style.right = "10px";
			
			document.body.appendChild(eThreadStats);
			
			if (imb.pageData.threadStats) {
				setThreadStats(imb.pageData.threadStats);
			}
		},
		
		relativeTimes: function () {
			var process;
			
			if (!$.fn.timeago) {
				return;
			}
			
			this.active = true;
			
			process = function (target) {
				b4k.forEach(target.querySelectorAll("time.timeago"), function (time) {
					$(time).timeago();
				});
			}
			
			$.timeago.settings.refreshMillis = 1000;
			$.timeago.settings.allowFuture = true;
			
			imb.utils.addPostProcessor(function (post) {
				process(post);
			});
			
			process(document);
		},
		
		styleTheme: function () {
			var setTheme;
			
			this.active = true;
			
			window.setTheme = this.setTheme = setTheme = function (theme) {
				document.body.dataset.theme = (theme ? theme : "");
				window.localStorage.theme = (theme ? theme : "");
			};
			
			setTheme(window.localStorage.theme);
		}
	};
	
	imb.utils = {
		runAllModules: function () {
			for (var i in imb.modules) {
				imb.modules[i] = new imb.modules[i];
			}
		},
		
		runOnAllNodes: function (callback) {
			callback(document);
			
			$(document).on("DOMNodeInserted", function (event) {
				if (event.target) {
					callback(event.target);
				}
			});
		},
		
		addPostProcessor: function (callback) {
			if (!imb.data.postProcessors) {
				imb.data.postProcessors = [];
			}
			
			imb.data.postProcessors.push(callback);
		},
		
		processPost: function (post) {
			if (imb.data.postProcessors) {
				for (var i = 0; i < imb.data.postProcessors.length; i++) {
					imb.data.postProcessors[i](post);
				}
			}
		},
		
		processExistingPosts: function () {
			var posts;
			
			posts = document.querySelectorAll(".post:not(.copy)");
			
			for (var i = 0; i < posts.length; i++) {
				imb.utils.processPost(posts[i]);
			}
		},
		
		addBoardLinksItem: function (text, callback) {
			var eLinkContainer;
			var eLinkInner;
			var boardLinksContainers;
			var boardLinksContainer;
			
			boardLinksContainers = document.querySelectorAll("body.board .board-links");
			
			if (!boardLinksContainers.length) {
				return;
			}
			
			eLinkContainer = document.createElement("span");
			
			eLinkInner = document.createElement("a");
			eLinkInner.textContent = text;
			eLinkInner.href = "#";
			
			$(eLinkInner).click(function (event) {
				event.preventDefault();
				callback();
			});
			
			b4k.appendTextNode(eLinkContainer, "[");
			eLinkContainer.appendChild(eLinkInner);
			b4k.appendTextNode(eLinkContainer, "]");
			
			for (var i = 0; i < boardLinksContainers.length; i++) {
				boardLinksContainer = boardLinksContainers[i];
				
				b4k.appendTextNode(boardLinksContainer, " ");
				
				boardLinksContainer.appendChild(b4k.cloneElement(eLinkContainer));
			}
		},
		
		makePostFromHTML: function (html) {
			var tempContainer;
			
			tempContainer = document.createElement("div");
			
			tempContainer.innerHTML = html;
			
			return (tempContainer.children[0] || null);
		}
	};
})();
