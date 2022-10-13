/* autocompletion handler for quick edit */
// noinspection SpellCheckingInspection

jQuery(async function ($) {

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
  let searchTerm

  const splitter = new RegExp('\\s+')

  /**
   * Sort the labels so items where the search term starts a name come first.
   * @param a
   * @param b
   * @returns {number}
   */
  function wordMatchesFirst(a, b) {
    const termStartsA = a.label.split(splitter).some(word => word.toLowerCase().startsWith(searchTerm.toLowerCase()))
    const termStartsB = b.label.split(splitter).some(word => word.toLowerCase().startsWith(searchTerm.toLowerCase()))
    if (termStartsA === termStartsB) {
      return a.label.localeCompare(b.label)
    }
    return termStartsA ? -1 : 1
  }

  /* parameters for REST query, from input.dataset */
  let dataset

  function fetch(req, res) {
    const endpoint = `/wp-json/wp/v2/users?context=view&who=authors&per_page=${dataset.count}&_fields=id,name&_locale=user`
    searchTerm = req.term
    const search = `&search=${req.term}`
    $.ajax(
      {
        url: endpoint + search,
        dataType: 'json',
        type: 'get',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', dataset.nonce);
        },
        success: function (data) {
          if (data.length === 0) {
            res(data);
          } else {
            const list = $.map(data, item => {
              return {label: item.name, value: item.name, id: item.id}
            })
            list.sort(wordMatchesFirst)
            res(list)
          }
        }
      }
    )
  }

  async function inlineClickHandler(event) {
    const theList = event.delegateTarget;
    await clickHandler(event, theList, 'inline')
  }

  async function clickHandler(event, theList, type) {
    await sleep(5);

    const select = $('tr.inline-edit-row.quick-edit-row select.index-wp-users-for-speed', theList)
    const selectElement = select[0]
    const autoCompleteField = $('#post_author-1')
    dataset = autoCompleteField[0].dataset
    autoCompleteField.autocomplete({
        delay: 500,
        appendTo: autoCompleteField.parent().parent().first(),
        source: fetch,
        create: function (event) {
          const target = event.target
          const selecteds = selectElement.selectedOptions
          if (selecteds.length === 1) {
            const selected = selecteds[0]
            target.dataset.id = selected.value
            if (typeof selected.label !== 'string' || selected.label.length === 0) {
              try {
                /* No label on the first option in the select (no name for the author).
                 * Go find the item being edited, then the item's author.
                 * Workaround for https://core.trac.wordpress.org/ticket/56819 */
                let editElement = selectElement
                while (editElement && !editElement.classList.contains('inline-edit-row')) {
                  editElement = editElement.parentNode
                }
                const id = editElement.id.split('-')[1]
                const authorName = $('#post-' + id + ' .author').text()
                target.dataset.p2 = authorName
                selected.value = authorName
              } catch (_) {
                target.dataset.p2 = target.dataset.p1
              }
            } else {
              target.dataset.p2 = selected.label
            }
            target.setAttribute('placeholder', target.dataset.p2)
          }
        },
        select:

          function (event, ui) {
            const chosen = ui.item
            const option = document.createElement("OPTION")
            option.innerText = chosen.label;
            option.value = chosen.id
            option.setAttribute('selected', '')
            for (const option of selectElement.options) {
              option.removeAttribute('selected')
            }
            selectElement.appendChild(option)
          }
      }
    )
    /* Handle the placeholder in the autocomplete field,
     * showing the current author, but switching to
     * "Type the name" instructions
     * when the field gets focus.
     */
    autoCompleteField[0].addEventListener('focus', event => {
      event.target.setAttribute('placeholder', event.target.dataset.p1);
    })
    autoCompleteField[0].addEventListener('blur', event => {
      event.target.setAttribute('placeholder', event.target.dataset.p2);
    })
  }

  $('#the-list').on('click', '.editinline', inlineClickHandler)

})
