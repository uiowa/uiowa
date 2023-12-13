import { Plugin } from 'ckeditor5/src/core';


class sitenowMediaWysiwyg extends Plugin {

  init() {
    // const { editor } = this;
    console.log('Sitenow media wysiwyg initialized.')
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'sitenowMediaWysiwyg';
  }
}

export default {
  sitenowMediaWysiwyg,
};
