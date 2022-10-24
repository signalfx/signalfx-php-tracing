add_library(zai_signalfx json_writer.c signalfx.c)

target_include_directories(
    zai_signalfx PUBLIC $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}>
                    $<INSTALL_INTERFACE:include>)

target_compile_features(zai_signalfx PUBLIC c_std_99)

target_link_libraries(zai_signalfx PUBLIC "${PHP_LIB}" dl)

set_target_properties(zai_signalfx PROPERTIES EXPORT_NAME SignalFX
                                            VERSION ${PROJECT_VERSION})

add_library(Zai::SignalFX ALIAS zai_signalfx)

install(
  FILES ${CMAKE_CURRENT_SOURCE_DIR}/json_writer.h
  FILES ${CMAKE_CURRENT_SOURCE_DIR}/signalfx.h
  DESTINATION ${CMAKE_INSTALL_INCLUDEDIR}/signalfx/)

target_link_libraries(zai_zend_abstract_interface INTERFACE zai_signalfx)

install(TARGETS zai_signalfx EXPORT ZendAbstractInterfaceTargets)
