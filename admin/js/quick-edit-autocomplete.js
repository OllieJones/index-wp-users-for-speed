/* autocompletion handler for quick edit */
jQuery(document).ready(async function ($) {

  const sleep = milliseconds => new Promise(r => setTimeout(r, milliseconds))
  /* Get an event when the user clicks quick-edit or, TODO, bulk-edit
   * and defer handling the event, using setTimeout, until the next time
   * through the Javascript main loop. That's because we may get our
   * event before wp-admin/js/inline-edit-post.js line 127 gets theirs,
   * and they insert the quick-edit code into the DOM, copying it from
   * #inline-edit.
   * For bulk edit <div class="hidden" id="inline_3932">   (the number is the post ID)
   *
   */

  //http://qa1.lan.plumislandmedia.net/wp-json/wp/v2/users?context=view&who=authors&per_page=50&_fields=id%2Cname&search=qa&_locale=user


  async function inlineClickHandler(event) {
    theList = event.delegateTarget;
    await clickHandler(event, theList, 'inline')
  }

  async function clickHandler(event, theList, type) {
    await sleep(5);

    const select = $('tr.inline-edit-row.quick-edit-row select.index-wp-users-for-speed', theList)
    const selectElement = select[0]
    const autoCompleteField = $('#post_author-1')
    toAppend = autoCompleteField.parent().parent().first();
    autoCompleteField.autocomplete({
      appendTo: toAppend,
      source: wp_iufs.completionList,
      focus: function (event, ui) {

        console.log('focus')
      },
      blur: function (event, ui) {
        console.log('blur')
      },
      open: function (event, ui) {
        const target = event.target

        console.log('open')
      },
      create: function (event, ui) {
        const target = event.target
        const selecteds = selectElement.selectedOptions
        if (selecteds.length === 1) {
          const selected = selecteds[0]
          target.dataset.id = selected.value
          target.dataset.p2 = selected.label
          target.setAttribute('placeholder', selected.label)
        }
      },
      close: function (event, ui) {
        console.log('open')
      },
      select: function (event, ui) {
        const chosen = ui.item
        const option = document.createElement("OPTION")
        option.innerText = chosen.label;
        option.value = chosen.id
        option.setAttribute('selected', true)
        for (const option of selectElement.options) {
          option.removeAttribute('selected')
        }
        selectElement.appendChild(option)
        const result = '<p>label: ' + chosen.label + ' -value: ' + chosen.value + '</p>'
        console.log(result)
      }
    })
    /* handle the placeholder in the autocomplete field */
    autoCompleteField[0].addEventListener('focus', event => {
      event.target.setAttribute('placeholder', event.target.dataset.p1);
    })
    autoCompleteField[0].addEventListener('blur', event => {
      event.target.setAttribute('placeholder', event.target.dataset.p2);
    })
  }

  //debugger
  $('#the-list').on('click', '.editinline', inlineClickHandler)

})
