/**
 * Makes links on Special:Moderation work via Ajax.
 */

(function () {
  "use strict";

  var api = new mw.Api(),
    $allRows = $(".modline");

  /**
   * Find subset of .modline elements by their $.data().
   * Note: $.data() of rows is populated in prepareRow().
   *
   * @param {string} dataKey
   * @param {string} dataVal
   * @return {jQuery}
   */
  function getRowsByData(dataKey, dataVal) {
    return $allRows.filter(function (idx, rowElem) {
      return $(rowElem).data(dataKey) === dataVal;
    });
  }

  /**
   * @param {string} modid
   * @return {jQuery}
   */
  function getRowById(modid) {
    return getRowsByData("modid", modid);
  }

  /**
   * @param {jQuery} $row
   * @return {jQuery}
   */
  function getRowsWithSameUser($row) {
    return getRowsByData("user", $row.data("user"));
  }

  /**
   * Calculate modid/username for this .modline.
   * Will then be used by runModaction() via getRows*() methods.
   *
   * @param {number} idx
   * @param {HTMLElement} rowElem
   */
  function prepareRow(idx, rowElem) {
    var $row = $(rowElem),
      $difflink = $row.find("a").first(),
      modid = new mw.Uri($difflink.attr("href")).query.modid,
      $userlink = $row.find(".mw-userlink"),
      username = $userlink.text();

    $row.data({
      user: username,
      modid: modid,
    });

    $row
      .addClass("modstatus-untouched")
      .prepend($("<span>").addClass("modicon"));
  }

  /**
   * Modify status icon of $rows.
   *
   * @param {jQuery} $rows
   * @param {string} type One of the following: untouched, processing, approved, rejected, error.
   * @param {string} tooltip Text shown when hovering over the icon.
   */
  function setRowsStatus($rows, type, tooltip) {
    $rows.attr("class", "modline modstatus-" + type);
    $rows.find(".modicon").attr("title", tooltip);
  }

  /**
   * Find action links in $rows.
   *
   * @param {jQuery} $rows
   * @param {string[]} actions E.g. [ 'approve', 'reject' ].
   * If contains 'ALL', all links are selected.
   * @return {jQuery}
   */
  function findLinks($rows, actions) {
    if (!actions.length) {
      return $([]); /* Empty jQuery collection */
    }

    var $links = $rows.find("a[data-modaction]");

    if (actions[0] !== "ALL") {
      $links = $links.filter(function (idx, linkElem) {
        var action = $(linkElem).attr("data-modaction");
        return actions.indexOf(action) !== -1;
      });
    }

    return $links;
  }

  /**
   * Enable/disable action links in $rows.
   *
   * @param {jQuery} $rows
   * @param {boolean} shouldBeEnabled If true, links will be enabled.
   * If false, they will be disabled.
   * @param {string[]} actions E.g. [ 'approve', 'reject' ].
   * If contains 'ALL', all links are affected.
   */
  function toggleLinks($rows, shouldBeEnabled, actions) {
    var $links = findLinks($rows, actions);

    /*
			Don't re-enable links that were disabled by successful action.
			For example, if Reject succeeded and then Approve resulted in an error,
			we should re-enable Approve link, but not Reject link.
		*/
    $links = $links.not(".modlink-not-applicable");

    if (shouldBeEnabled) {
      $links.removeClass("modlink-disabled");
    } else {
      $links.addClass("modlink-disabled");
    }
  }

  /**
   * Determine which links should be disabled by successful action.
   *
   * @param {string} action Action name, e.g. 'approve' or 'reject'.
   * @return {string[]}
   */
  function getDisabledActions(action) {
    var disabledActions = [];
    switch (action) {
      case "approve":
      case "approveall":
        /* Approval completely deletes the row from Special:Moderation,
					all actions (even "diff") won't be applicable */
        disabledActions = ["ALL"];
        break;

      case "reject":
      case "rejectall":
        /* Rejected edit can still be approved, but not via "Approve all" */
        disabledActions = ["reject", "rejectall", "approveall"];
        break;
    }

    return disabledActions;
  }

  /**
   * Mark the edit as rejected. Called if Reject(all) returned success.
   *
   * @param {jQuery} $rows List of .modline elements.
   */
  function markRejected($rows) {
    setRowsStatus($rows, "rejected", ""); /* TODO: add tooltip */
  }

  /**
   * Mark the edit as approved. Called if Approve(all) returned success.
   *
   * @param {jQuery} $rows List of .modline elements.
   */
  function markApproved($rows) {
    setRowsStatus($rows, "approved", ""); /* TODO: add tooltip */
  }

  /**
   * Mark the row as unsuccessfully modified.
   *
   * @param {jQuery} $rows
   * @param {string} errorText
   * @param {string} action
   */
  function markError($rows, errorText, action) {
    setRowsStatus($rows, "error", errorText);

    /* Re-enable disabledActions, unless they were disabled before. */
    toggleLinks($rows, true, getDisabledActions(action));
  }

  /**
   * Update Special:Moderation after a successful Ajax call.
   *
   * @param {Object[]} $rows The .modline elements affected by this action.
   * @param {Object} q Query, e.g. { modid: 123, modaction: 'reject' }
   * @param {Object} ret Parsed JSON response, as returned by the API.
   */
  function handleSuccess($rows, q, ret) {
    switch (q.modaction) {
      case "reject":
      case "rejectall":
        markRejected($rows);
        break;

      case "approve":
        markApproved($rows);
        break;

      case "approveall":
        var modid;
        for (modid in ret.moderation.failed) {
          markError(
            getRowById(modid),
            ret.moderation.failed[modid].info,
            "approveall",
          );
        }

        for (modid in ret.moderation.approved) {
          markApproved(getRowById(modid));
        }

        break;

      case "block":
      case "unblock":
        /* Replace 'block' link with 'unblock' link, and vise versa */
        var newAction = q.modaction === "block" ? "unblock" : "block";

        var $links = findLinks($rows, ["block", "unblock"]);
        $links.attr("data-modaction", newAction).each(function (idx, linkElem) {
          linkElem.href = linkElem.href.replace(q.modaction, newAction);
        });

        // Messages used here (for grep):
        // * moderation-block
        // * moderation-unblock
        $links.text(mw.msg("moderation-" + newAction));
        break;

      default:
        console.log(
          'Post-Ajax handler: not implemented for modaction="' +
            q.modaction +
            '"',
        );
    }

    /* Mark disabled links as permanently disabled (unaffected by toggleLinks).
			E.g. Reject is no longer applicable if Approve was successful.
		*/
    $rows.find("a.modlink-disabled").addClass("modlink-not-applicable");
  }

  /**
   * Handle the click on modaction link (e.g. "Reject").
   *
   * @param {Event} ev
   */
  function runModaction(ev) {
    var $link = $(this);
    // eslint-disable-next-line no-jquery/no-class-state
    if ($link.hasClass("modlink-disabled")) {
      ev.preventDefault();
      return; /* Action is no longer possible, e.g. "Reject" on already approved row */
    }

    var action = $link.attr("data-modaction");
    if (action === "show" || action === "merge" || action === "preview") {
      return; /* Non-Ajax action */
    }

    ev.preventDefault();

    var $row = $(this).parent(".modline");

    /* Prepare API parameters */
    var q = {
      action: "moderation",
      modaction: action,
      modid: $row.data("modid"),
    };

    var $affectedRows = $row; /* Rows that need "processing" icon */
    if (
      action === "approveall" ||
      action === "rejectall" ||
      action === "block" ||
      action === "unblock"
    ) {
      $affectedRows = getRowsWithSameUser($row);

      if (action === "approveall" || action === "rejectall") {
        /* Approved/rejected rows are not affected by ApproveAll/RejectAll */
        $affectedRows = $affectedRows.not(
          ".modstatus-approved,.modstatus-rejected",
        );
      }
    }

    if (action !== "block" && action !== "unblock") {
      setRowsStatus($affectedRows, "processing", "...");
    }

    /* Disable action links that will no longer be applicable after this action.
			For example, after the row is approved, it can no longer be rejected. */
    toggleLinks($affectedRows, false, getDisabledActions(action));

    api
      .postWithToken("csrf", q)
      .done(function (ret) {
        handleSuccess($affectedRows, q, ret);
      })
      .fail(function (code, ret) {
        console.log("Moderation: ajax error: ", JSON.stringify(ret));
        markError($affectedRows, ret.error.info, action);
      });
  }

  /* Scan rows on Special:Moderation, install Ajax handlers */
  $allRows
    .each(prepareRow)
    .find('a[href*="modaction"]')
    .each(function (idx, linkElem) {
      /* Extract modaction from href */
      $(linkElem).attr(
        "data-modaction",
        new mw.Uri(linkElem.href).query.modaction,
      );
    })
    .on("click", runModaction);
})();
