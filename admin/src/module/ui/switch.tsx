import { $, Rocet } from '@rocet/rocet';
import { integ } from '@rocet/integration';
import { RocetNode } from '@rocet/RocetNode';

export function switchFormRender(Rocet: Rocet, i: number) {
    const el = $(Rocet.Elements[i]);
    if (el.attr('render')) return;
    el.attr('render', '1');

    const input: RocetNode = <input /> as RocetNode;
    input.props = {
        type: 'checkbox',
        name: el.attr('name'),
        value: el.attr('value') || '1',
        checked: el.isAttr('checked'),
        disabled: el.isAttr('disabled'),
        className: 'ui-switch__input',
    };

    return (
        <div className="form-switch">
            <span className="form-switch__label">{el.attr('label')}</span>
            <label className="ui-switch">
                {input}
                <span className="ui-switch__track"></span>
            </label>
        </div>
    );
}

$('input[ui=switch]').render(switchFormRender);
