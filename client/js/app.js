IdeaSlides = Ember.Application.create({
	LOG_TRANSITIONS: true	
});

/* Model */

IdeaSlides.ApplicationAdapter = DS.RESTAdapter.extend({
	host: 'http://localhost/ideaSlides/php/index.php'
});

IdeaSlides.Presentation = DS.Model.extend({
	slides: DS.hasMany('slide'),
	title: DS.attr('string'),
	description: DS.attr('string'),
	sort: DS.attr('number')
});

IdeaSlides.Slide = DS.Model.extend({	
	title: DS.attr('string'),
	category: DS.attr('string'),
	body: DS.attr('string'),
	code: DS.attr('string'),
	previous: DS.attr('number'),
	next: DS.attr('number'),
	number: DS.attr('number'),
	total: DS.attr('number'),
	presentation: DS.belongsTo('presentation'),
	percent: function() {
		return parseInt(this.get('number') * 100 / this.get('total')); 
    }.property('number', 'total'),
    sort: DS.attr('number')
});

/* Router */

IdeaSlides.Router.map(function() {
	this.resource('presentations', function() {
		this.resource('presentation', { path: '/:presentation_id' }, function() {
			this.resource('slides', function() {
				this.route('new');
				this.route('create');
			});
			this.route('slide', { path: 'slides/:slide_id'});
			this.route('edit');
		});
		this.route('new');
	});
});

IdeaSlides.IndexRoute = Ember.Route.extend({
	redirect: function(){
		this.transitionTo('presentations');
	}
});

IdeaSlides.PresentationsRoute = Ember.Route.extend({
	model: function() {
		return this.get('store').find('presentation');
	},
	setupController: function(controller, model) {
    	controller.set('content', model);
    }
});

IdeaSlides.PresentationIndexRoute = Ember.Route.extend({
	model: function() {
    	return this.modelFor('presentation');
    },
    afterModel: function() {
		// Update presentation selector
		Ember.run.scheduleOnce('afterRender', this, function() {
			var presentation = this.modelFor('presentation');
			var selected = presentation.get('id');
			
			var options = presentationSelector.options;
			
			for (var i = 0; i < options.length; i++)
				if (options[i].value == selected) {
					presentationSelector.selectedIndex = i;
					break;
				}
		});
		
		var slides = this.modelFor('presentation').get('slides');

		if (slides.get('length') > 0)
			this.transitionTo('presentation.slide', slides.get('firstObject'));
	}
});

IdeaSlides.SlidesIndexRoute = Ember.Route.extend({
	model: function() { 
		return this.modelFor('presentation');
	}
});

IdeaSlides.SlidesNewRoute = Ember.Route.extend({
	setupController: function(controller, model) {
		controller.set('title', null);
		controller.set('category', null);
		controller.set('body', null);
		controller.set('code', null);
		controller.set('sort', null);
	}	
});

IdeaSlides.PresentationEditRoute = Ember.Route.extend({
	model: function() {
		return this.modelFor('presentation');
	}
});

IdeaSlides.PresentationSlideRoute = Ember.Route.extend({
	afterModel: function(slide) {
		this.modelFor('presentation').get('slides').forEach(function(item, index, enumerable) {
			item.set('isShown', false);
		});
		
		slide.set('isShown', true);
			
		// Update presentation selector
		Ember.run.scheduleOnce('afterRender', this, function() {
			var presentation = this.modelFor('presentation');
			
			if (presentation) {
				var selected = presentation.get('id');
				
				var options = presentationSelector.options;
				
				for (var i = 0; i < options.length; i++)
					if (options[i].value == selected) {
						presentationSelector.selectedIndex = i;
						break;
					}
			}
		});
		
		// Key handler
		var previous = slide.get('previous');
		var next = slide.get('next');
		
		var route = this;		
		
		function onkeydown(event) {
			switch(event.keyCode) {
				case 38:
				case 37:
					if (previous)
						route.transitionTo('presentation.slide', previous);
					break;
				case 40:
				case 39:
					if (next)
						route.transitionTo('presentation.slide', next);
					break;
			}
		}
		
		if (window.attachEvent)
			window.attachEvent('keydown', onkeydown);
		else
			window.addEventListener('keydown', onkeydown, false);
			
		// Souce code load
		
		var head = document.getElementsByTagName('head')[0];
		var code = slide.get('code');
		var script = document.createElement('script');
		script.innerHTML = code;
		head.appendChild(script);
		
		// Prettify source code
		Ember.run.scheduleOnce('afterRender', this, function() {
		/*	var elements = document.querySelectorAll('div.preview pre');
		
			for (var i = 0; i < elements.length; i++)
				elements[i].className = "prettyprint linenums lang-css";
			*/	
			prettyPrint();
		});
	}
});

/* Controllers */

IdeaSlides.ApplicationController = Ember.ObjectController.extend({
	isEditing: true,
	isPreviewing: false,
	actions: {
		edition: function() {
			this.set('isEditing', true);
			this.set('isPreviewing', false);
		},
		preview: function() {
			this.set('isEditing', false);
			this.set('isPreviewing', true);
		}
	}
});

IdeaSlides.PresentationsController = Ember.ObjectController.extend({
	needs: ['application'],
	isEditing: Ember.computed.alias('controllers.application.isEditing'),
	isPreviewing: Ember.computed.alias('controllers.application.isPreviewing'),
	presentation: null,
	selectionChanged: function() { 
		var presentation = this.get('presentation');
		
		if (presentation)
			this.transitionToRoute('presentation', presentation);
	}.observes('presentation'),
	total: function() {
		return this.get('length');
	}.property('@each'),
	actions: {
		editPresentation: function() {
			this.transitionToRoute('presentation.edit');
		}
	}
});

IdeaSlides.PresentationController = Ember.ObjectController.extend({
	needs: ['application'],
	isEditing: Ember.computed.alias('controllers.application.isEditing'),
	isPreviewing: Ember.computed.alias('controllers.application.isPreviewing'),
	total: function() {
		return this.get('length');
	}.property('@each')
});

IdeaSlides.PresentationSlideController = Ember.ObjectController.extend({
	needs: ['application'],
	isEditing: Ember.computed.alias('controllers.application.isEditing'),
	isPreviewing: Ember.computed.alias('controllers.application.isPreviewing'),
	percentStyle: function() {
		var slide = this.get('model');
		var percent = slide.get('percent');
		
		return "width:" + percent + "%;";
	}.property('percent'),
	actions: {
		delete: function() {
			var conf = confirm("¿Está seguro de borrar la diapositiva?");

			if (!conf)
				return;
		
    		var slide = this.get('model');
    		
    		var presentation = slide.get('presentation');
    		var previous = slide.get('previous');
		
    		slide.deleteRecord();
	    	slide.save();
	    	
	    	//this.get('store').find('slide'); // Refresh data to reload links
	    	
	    	this.transitionToRoute('presentation.index', presentation);
		},
		save: function() {
			this.get('model').save();
		},
		acceptChanges: function() {
    		this.get('model').save();
		}
	}
});

IdeaSlides.SlidesNewController = Ember.ObjectController.extend({
	needs: 'presentation',
	title: null,
	category: null,
	body: null, 
	code: null,
	sort: null,
	actions: {
		save: function() {
			var presentation = this.get('controllers.presentation.content');
			var slide = this.get('store').createRecord('slide', { presentation: presentation, title: this.get('title'), category: this.get('category'), body: this.get('body'), code: this.get('code'), sort: this.get('sort') });
			slide.save().then(function(slide) {
				presentation.get('slides').pushObject(slide);
			});
			this.get('target').transitionTo('presentation.index');
		}
	}
});

IdeaSlides.PresentationsNewController = Ember.ObjectController.extend({
	title: null,
	description: null,
	sort: null,
	actions: {
    	save: function(presentation) {
	    	var me = this;
	    	var presentation = this.get('store').createRecord('presentation', { title: this.get('title'), description: this.get('description'), sort: this.get('sort') });
	    	presentation.save().then(function(presentation) {
		    	me.get('target').transitionTo('presentations.index');
		    });
		}
	}
});

IdeaSlides.PresentationEditController = Ember.ObjectController.extend({
	actions: {
		save: function() {
			this.get('model').save();
		},
		delete: function() {
			var conf = confirm("¿Está seguro de borrar la presentación?");

			if (!conf)
				return;
		
    		var presentation = this.get('model');
    		
    		presentation.deleteRecord();
	    	presentation.save();
	    	
	    	this.transitionToRoute('/');
		}
	}
});

/* Helpers */

var showdown = new Showdown.converter();

Ember.Handlebars.registerBoundHelper('markdown', function(input) {
	return input && input.trim() ? new Ember.Handlebars.SafeString(showdown.makeHtml(input)) : input;
});