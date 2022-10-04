// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript module for bulk actions.
 *
 * @module      gradereport_singleview/bulkactions
 * @copyright   2022 Ilya Tregubov <ilya@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';
import ModalEvents from 'core/modal_events';

/**
 * Initialize module.
 */
export const init = () => {
    document.querySelector('.action-menu').addEventListener('click', function(e) {

        const roleHolder = e.target.closest('[data-role]');

        e.preventDefault();

        if ((roleHolder.dataset.role === 'overrideallgrades') || (roleHolder.dataset.role === 'overridenonegrades')) {
            const checked = (roleHolder.dataset.role === 'overrideallgrades');
            const overrideCheckbox = document.querySelectorAll('input[type=checkbox][name^=override]');
            const overrideGrade = document.querySelectorAll('input[type=text][name^=finalgrade]');

            if (roleHolder.dataset.role === 'overridenonegrades') {
                const confirm = new M.core.confirm({
                    title:      M.util.get_string('confirm', 'moodle'),
                    question:   M.util.get_string('overridenoneconfirm', 'gradereport_singleview'),
                });

                confirm.on('complete-yes', function() {
                    confirm.hide();
                    confirm.destroy();

                    const overrideCheckbox = document.querySelectorAll('input[type=checkbox][name^=override]');
                    const overrideGrade = document.querySelectorAll('input[type=text][name^=finalgrade]');
                    for (let i = 0; i < overrideGrade.length; i++) {
                        overrideGrade[i].disabled = !checked;
                        overrideCheckbox[i].checked = checked;
                    }
                }, self);
                confirm.show();
            } else {
                for (let i = 0; i < overrideGrade.length; i++) {
                    overrideGrade[i].disabled = !checked;
                    overrideCheckbox[i].checked = checked;
                }
            }
        } else if ((roleHolder.dataset.role === 'excludeallgrades') || (roleHolder.dataset.role === 'excludenonegrades')) {
            const checked = (roleHolder.dataset.role === 'excludeallgrades');
            const excludeCheckbox = document.querySelectorAll('input[type=checkbox][name^=exclude]');
            for (let i = 0; i < excludeCheckbox.length; i++) {
                excludeCheckbox[i].checked = checked;
            }
        } else if (roleHolder.dataset.role === 'bulklegend') {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                body: Templates.render('gradereport_singleview/bulkinsert', {}),
                title: 'Bulk insert',
            })
                .then(function(modal) {
                    modal.setSaveButtonText('Save');

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().remove();
                    });

                    modal.getRoot().on(ModalEvents.save, function(e) {
//                        let formData = modal.getRoot().find('.form-control').val();
                        let formData = e.target.baseURI;
                        //const bulk = "bulk_" + "_apply";
                        window.location.href = formData + "&bulkinsert=1";
                        //                        modal.getRoot().find('form').submit();
                    });

                    modal.show();

                    return modal;
                });
        }
    });
};
