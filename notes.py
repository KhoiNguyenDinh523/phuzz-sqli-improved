class SQLiVulnCheck(VulnCheck):
    NAME = "SQLi"

    def __init__(self, mysql_errors_folder):
        self.mysql_errors_folder = mysql_errors_folder

    def check(self, candidate):
        sqli_file = os.path.join(
            self.mysql_errors_folder, f"{candidate.coverage_id}.json"
        )
        if os.path.isfile(sqli_file):
            return True
        return False

class ParamBasedSQLiVulnCheck(VulnCheck):
    NAME = "SQLi"

    def __init__(self, mysql_errors_folder):
        self.mysql_errors_folder = mysql_errors_folder

    def check(self, candidate):
        sqli_file = os.path.join(
            self.mysql_errors_folder, f"{candidate.coverage_id}.json"
        )
        if not os.path.isfile(sqli_file):
            return False

        for line in fuzz_open(sqli_file):
            if not line.strip():
                continue
            error = json.loads(line)
            for error_param in error['params']:
                if not error_param:
                    continue
                for vuln_type in candidate.fuzz_params.keys():
                    for pkey, pval in candidate.fuzz_params[vuln_type].items():
                        if pval in error_param:
                            return True
        return False