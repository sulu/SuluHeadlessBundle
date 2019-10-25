import React from 'react';
import { observer } from 'mobx-react';
import viewRegistry from '../registries/viewRegistry';
import viewDataStore from '../stores/viewDataStore';

@observer
class ViewRenderer extends React.Component {
    render() {
        const {
            type: currentViewType,
            template: currentViewTemplate,
            data: currentViewData,
        } = viewDataStore;

        if (!currentViewType || !currentViewTemplate) {
            return null;
        }

        const ViewComponent = viewRegistry.get(currentViewType, currentViewTemplate);

        return (<ViewComponent data={currentViewData} />);
    }
}

export default ViewRenderer;
