/**
 * ElaticSearch tracking indexer plugin. Module declaration.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @license   Apache License Version 2.0
 */
package eu.smile.es.tracking.collect;
import org.elasticsearch.common.inject.AbstractModule;

/**
 * ElasticSearch tracking indexation plugin module
 * 
 * @author Aurelien FOUCRET <aufou@smile.fr>
 */
public class CollectModule extends AbstractModule {
	
	/**
	 * Install the module
	 */
    @Override
    protected void configure() {
        bind(CollectRestHandler.class).asEagerSingleton();
    }
  
}