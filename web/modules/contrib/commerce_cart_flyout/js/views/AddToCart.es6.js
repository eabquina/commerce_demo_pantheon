(($, Backbone, _, Drupal) => {
  Drupal.addToCart.AddToCartView = Backbone.View.extend(/** @lends Drupal.cartFlyout.CartBlockView# */{
    initialize() {
      const defaultVariation = this.model.getVariation(this.model.getDefaultVariation());
      _.each(this.model.getAttributes(), (attribute, i) => {
        let attributeFieldName = 'attribute_' + attribute.id;
        if (defaultVariation.hasOwnProperty(attributeFieldName)) {
          this.selectedAttributes[attributeFieldName] = defaultVariation[attributeFieldName];
        }
      });
      this.render();
    },
    events: {
      'click .form-submit': 'addToCart',
      'change .attribute-widgets input[type="radio"]': 'onAttributeChange',
      'change .attribute-widgets select': 'onAttributeChange',
      'change .variations-select select': 'onVariationTitleChange',
    },
    onVariationTitleChange(event) {
      Drupal.detachBehaviors();
      const selectedVariation = this.model.getVariation(event.target.value);
      this.model.setSelectedVariation(selectedVariation.uuid);
      const injectedFields = this.model.getInjectedFieldsForVariation(selectedVariation.uuid);
      Object.values(injectedFields).map(function (injectedField) {
        $('.' + injectedField.class).html(injectedField.output);
      });
      Drupal.attachBehaviors();
    },
    onAttributeChange(event) {
      Drupal.detachBehaviors();
      this.selectedAttributes[event.target.name] = event.target.value;
      const selectedVariation = this.model.getResolvedVariation(this.selectedAttributes);
      this.model.setSelectedVariation(selectedVariation.uuid);
      const injectedFields = this.model.getInjectedFieldsForVariation(selectedVariation.uuid);
      Object.values(injectedFields).map(function (injectedField) {
        $('.' + injectedField.class).html(injectedField.output);
      });
      Drupal.attachBehaviors();
    },
    addToCart() {
      const selectedVariation = this.model.getSelectedVariation();
      $.ajax({
        url:Drupal.url(`cart/add?_format=json`),
        method: 'POST',
        data: JSON.stringify([
          {
            purchased_entity_type: 'commerce_product_variation',
            purchased_entity_id: selectedVariation.variation_id,
            quantity: 1
          }
        ]),
        contentType: `application/json;`,
        dataType: `json`,
      })
        .done(() => {
          Drupal.cartFlyout.fetchCarts();
          Drupal.cartFlyout.flyoutOffcanvasToggle();
        });
    },
    render() {
      if (this.model.getVariationCount() === 1) {
        this.$el.html(Drupal.theme('addToCartButton'));
      } else if (this.model.getAttributes().length === 0 || this.model.getType() !== 'commerce_product_variation_attributes') {
        let html = [
          '<div class="variations-select form-group">'
        ];

        const variations = this.model.getVariations();
        html.push(Drupal.theme('addToCartVariationSelect', {
          variations: Object.keys(variations).map(uuid => variations[uuid])
        }))

        html.push('</div>');
        html.push(Drupal.theme('addToCartButton'));
        this.$el.html(html.join(''));
      } else {
        const view = this;
        let html = [
          '<div class="attribute-widgets form-group">'
        ];
        this.model.getAttributes().forEach(entry => {
          const defaultArgs = {
            label: entry.label,
            attributeId: entry.id,
            attributeValues: entry.values,
            activeValue: view.selectedAttributes['attribute_' + entry.id]
          };

          if (entry.element_type === 'select') {
            html.push(Drupal.theme('addToCartAttributesSelect', defaultArgs))
          } else if (entry.element_type === 'radios') {
            html.push(Drupal.theme('addToCartAttributesRadios', defaultArgs))
          } else if (entry.element_type === 'commerce_product_rendered_attribute') {
            html.push(Drupal.theme('addToCartAttributesRendered', Object.assign({}, defaultArgs, {
              attributeValues: view.model.getRenderedAttribute('attribute_' + entry.id)
            })))
          }
        });
        html.push('</div>');
        html.push(Drupal.theme('addToCartButton'));
        this.$el.html(html.join(''));
      }
    }
  });
  Drupal.addToCart.AddToCartView.prototype.selectedAttributes = {};
})(jQuery, Backbone, _, Drupal);
