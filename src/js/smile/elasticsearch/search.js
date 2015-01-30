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