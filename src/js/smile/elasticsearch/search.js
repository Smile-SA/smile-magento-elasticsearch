/**
 * ElasticSearch features javascript
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
 
Es = {};

Es.searchForm = Class.create(Varien.searchForm, {
    initAutocomplete : function(url, destinationElement) {
        new MultipleAutoCompleter(this.field, destinationElement, url, {
            paramName : this.field.name,
            method : 'get',
            minChars : 2,
            updateElement : this._selectAutocompleteItem.bind(this),
            onShow : function(element, update) {
                if (!update.style.position
                        || update.style.position == 'absolute') {
                    update.style.position = 'absolute';
                    Position.clone(element, update, {
                        setHeight : false,
                        offsetTop : element.offsetHeight
                    });
                }
                Effect.Appear(update, {
                    duration : 0
                });
            },
            autoSelect : false
        });
    },
    _selectAutocompleteItem : function(element) {
        if (element.hasAttribute('href')) {
            window.location = element.getAttribute('href');
        } else {
            if (element.title) {
                this.field.value = element.title;
            }
            this.form.submit();
        }
    }
});

MultipleAutoCompleter = Class.create(Ajax.Autocompleter, {
    getEntry : function(index) {
        return this.update.firstChild.select('dd')[index];
    },
    updateChoices : function(choices) {
        if (!this.changed && this.hasFocus) {
            this.update.innerHTML = choices;
            Element.cleanWhitespace(this.update);
            Element.cleanWhitespace(this.update.down());

            if (this.update.firstChild && this.update.firstChild.select('dd')) {
                this.entryCount = this.update.firstChild.select('dd').length;
                for ( var i = 0; i < this.entryCount; i++) {
                    var entry = this.getEntry(i);
                    entry.autocompleteIndex = i;
                    this.addObservers(entry);
                }
            } else {
                this.entryCount = 0;
            }

            this.stopIndicator();

            // Avoid automatic first suggestion selection
            this.index = -1;

            if (this.entryCount == 1 && this.options.autoSelect) {
                this.selectEntry();
                this.hide();
            } else {
                this.render();
            }
        }
    },

    onHover : function(event) {
        var element = Event.findElement(event, 'DD');
        this.index = element.autocompleteIndex;
        this.render();
        Event.stop(event);
    },

    onClick : function(event) {
        var element = Event.findElement(event, 'DD');
        this.index = element.autocompleteIndex;
        this.selectEntry();
        this.hide();
    }
});


Es.rangeSlider = function(config) {
    var sliderRootNode = config.rootNode.select('.slider-bkg')[0];
    var inputNodes     = config.rootNode.select('span.limits');
    var validButton    = config.rootNode.select('button.valid')[0];
    var counterNode    = config.rootNode.select('em.count')[0];
    var countProducts  = 0;
    config.valueFormat = config.valueFormat != null ? config.valueFormat : function(value) {return Math.trunc(value)};

    function initSlider() {
        var slider = new Control.Slider(sliderRootNode.select('.handle'), sliderRootNode, {
            range       : config.range,
            sliderValue : config.values,
            restricted  : true,
            onSlide     : onSliderChangeValues,
            onChange    : onSliderChangeValues
        });
        return slider;
    }

    var slider = initSlider();

    function onSliderChangeValues(values) {
        for (var i=0; i < values.length; i++) {
            inputNodes[i].innerHTML = config.valueFormat(values[i]);
        }
        config.values = values;
        updateCounter(values);
    }

    validButton.observe('click', function() {
        var values     = {min : parseInt(slider.values[0]), max: parseInt(slider.values[1])};
        var template   = config.filterTemplate;
    
        var urlToken   = template.evaluate(values);
        var addedParams = false;
    
        var search = window.location.search.substring(1).split('&').map(function(part) {
            part = part.split('=');
            if (part[0] == config.requestVar) {
                addedParams = true;
                return template.evaluate(values);
            } else {
                return part.join('=');
            }
        }).join('&');
    
        if (addedParams == false) {
            search = search.length == 0 ? template.evaluate(values) : search + "&" + template.evaluate(values);
        }
    
        window.location.search = '?' + search;
    })

    function updateCounter(values) {

        countProducts = 0;

        for (var i=0; i < config.allowedIntervals.length; i++) {
            var currentValue = config.allowedIntervals[i].value;

            if (values[0] <= currentValue && values[1] >= currentValue) {
                countProducts += config.allowedIntervals[i].count;
            }
        }

        counterNode.innerHTML = countProducts;

        countTemplate = 'empty';

        if (countProducts > 1) {
            countTemplate = 'multiple';
            validButton.show();
        } else if (countProducts > 0) {
            countTemplate = 'one';
            validButton.show();
        } else {
            validButton.hide();
        }

        counterNode.innerHTML = config.countProductTemplates[countTemplate].evaluate({count: countProducts})
        counterNode.removeClassName('one'); counterNode.removeClassName('multiple'); counterNode.removeClassName('empty');
        counterNode.addClassName(countTemplate);
    }
    $$('.block-subtitle.block-subtitle--filter, .block.block-layered-nav dl > dt').each(function(node) {
        node.addEventListener('click', function() {setTimeout(initSlider, 30);});
    });
    window.addEventListener('resize', initSlider);
    onSliderChangeValues(slider.values);
};